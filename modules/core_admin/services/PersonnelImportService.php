<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Crypto;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Session;
use Core\Validator;
use Modules\CoreAdmin\Models\EmployeeModel;

/**
 * นำเข้าบุคลากรจาก CSV แบบ 2 ขั้น: (1) อัปโหลด → ตรวจทุกแถว → แสดงตัวอย่าง (2) ยืนยัน → บันทึก
 * ไฟล์ไม่ถูกเก็บบนโฮสต์ (อ่านจาก tmp แล้วทิ้ง) ข้อมูลรอยืนยันเก็บใน Session แบบเข้ารหัส AES-256-GCM
 */
final class PersonnelImportService
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_ROWS = 1000;
    private const SESSION_KEY = '_personnel_import';
    private const TTL = 900;

    /** หัวคอลัมน์ของไฟล์ต้นแบบ (department_code ใช้รหัสหรือชื่อฝ่ายก็ได้) */
    public const COLUMNS = [
        'employee_code', 'title_name', 'first_name', 'last_name', 'nickname', 'gender', 'birth_date',
        'position', 'position_number', 'academic_rank', 'employment_type', 'education_level', 'education_major', 'screener_reg_no',
        'license_no', 'license_expiry_date', 'first_position', 'appointment_date',
        'department_code', 'service_unit_code', 'phone', 'email', 'address', 'start_date', 'end_date', 'status', 'note', 'national_id',
    ];

    public static function templateCsv(): string
    {
        $example = [
            '', 'นาง', 'สมใจ', 'ใจดี', 'ใจ', 'หญิง', '2528-04-12', 'ครู', '12345', 'ครูชำนาญการ',
            'ข้าราชการ', 'ปริญญาโท', 'การศึกษาพิเศษ', '', 'ค12345678', '2572-03-31', 'ครูผู้ช่วย', '2555-05-16',
            'ACADEMIC', 'SU_PONG', '0812345678', 'somjai@py-sec.ac.th', '',
            '2555-05-16', '', 'ปฏิบัติงาน', '', '',
        ];

        return "\xEF\xBB\xBF" . self::csvLine(self::COLUMNS) . self::csvLine($example);
    }

    /**
     * @return array{rows: list<array>, errors: array<int, list<string>>, creates: int, updates: int}
     */
    public static function preview(array $file, bool $updateExisting): array
    {
        $records = self::parse($file);
        $departments = self::departmentLookup();
        $seen = ['employee_code' => [], 'email' => [], 'national_id' => []];
        $rows = [];
        $errors = [];
        $creates = 0;
        $updates = 0;

        foreach ($records as $index => $record) {
            $line = $index + 2; // บรรทัดที่ 1 คือหัวคอลัมน์
            try {
                $data = self::normalize($record, $departments);
                $code = (string) $data['employee_code'];
                $absent = $data['_absent'];
                $education = $data['_education'];
                $data = Validator::validate($data, PersonnelService::rules(), PersonnelService::LABELS);
                $data['_absent'] = $absent;
                $data['_education'] = $education;
                $data['employee_code'] = $code !== '' ? $code : null; // ว่าง = บุคลากรใหม่ ระบบสร้างรหัสให้
                PersonnelService::assertDepartmentAllowed((int) $data['department_id']);

                foreach (['employee_code', 'email', 'national_id'] as $key) {
                    $value = $data[$key] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    $value = $key === 'national_id'
                        ? (string) preg_replace('/\D/', '', (string) $value)
                        : mb_strtolower(trim((string) $value));
                    if (isset($seen[$key][$value])) {
                        throw new ValidationException([$key => [PersonnelService::LABELS[$key] . ' ซ้ำกับบรรทัดที่ ' . $seen[$key][$value]]]);
                    }
                    $seen[$key][$value] = $line;
                }

                $existing = null;
                $action = 'create';
                if ($code !== '') {
                    $existing = EmployeeModel::findByCode($code) ?? throw new ValidationException(['employee_code' => [
                        "ไม่พบรหัสบุคลากร {$code} (บุคลากรใหม่ให้เว้นว่าง ระบบจะสร้างรหัสให้อัตโนมัติ)",
                    ]]);
                    $action = self::assertUpdatable($existing, $data, $updateExisting);
                } elseif (!PersonnelService::canCreate()) {
                    throw new ValidationException(['employee_code' => ['คุณไม่มีสิทธิ์เพิ่มบุคลากรใหม่']]);
                }
                $nidHash = !empty($data['national_id'])
                    ? PersonnelService::nationalIdHash((string) preg_replace('/\D/', '', (string) $data['national_id']))
                    : null;
                PersonnelService::assertUnique($data, $existing !== null ? (int) $existing['id'] : null, $nidHash);

                $action === 'create' ? $creates++ : $updates++;
                $rows[] = ['line' => $line, 'action' => $action, 'data' => $data];
            } catch (ValidationException $e) {
                $errors[$line] = array_merge(...array_values($e->errors()));
            } catch (HttpException $e) {
                $errors[$line] = [$e->getMessage()];
            }
        }

        if ($rows === [] && $errors === []) {
            throw new ValidationException(['file' => ['ไม่พบข้อมูลในไฟล์ (มีเฉพาะหัวคอลัมน์)']]);
        }

        if ($errors === []) {
            Session::set(self::SESSION_KEY, [
                'payload' => Crypto::encrypt(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'created' => time(),
            ]);
        } else {
            Session::forget(self::SESSION_KEY);
        }

        return ['rows' => $rows, 'errors' => $errors, 'creates' => $creates, 'updates' => $updates];
    }

    /** @return array{created: int, updated: int} */
    public static function commit(): array
    {
        $pending = Session::get(self::SESSION_KEY);
        Session::forget(self::SESSION_KEY);
        if (!is_array($pending) || (time() - (int) ($pending['created'] ?? 0)) > self::TTL) {
            throw new ValidationException(['file' => ['ข้อมูลรอยืนยันหมดอายุ กรุณาอัปโหลดไฟล์ใหม่']]);
        }
        $rows = json_decode(Crypto::decrypt((string) $pending['payload']), true, 512, JSON_THROW_ON_ERROR);

        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            // ตรวจซ้ำตอนบันทึกจริง (ข้อมูลอาจเปลี่ยนระหว่างรอยืนยัน) — ล้มที่แถวใดจะแจ้งบรรทัดนั้น
            try {
                $code = (string) ($row['data']['employee_code'] ?? '');
                $existing = $code !== '' ? EmployeeModel::findByCode($code) : null;
                if ($row['action'] === 'update' && $existing !== null) {
                    $current = EmployeeModel::findDetail((int) $existing['id']);
                    if ($current === null || !PersonnelService::canUpdate($current)) {
                        throw new HttpException(403);
                    }
                    // คอลัมน์ที่ไม่มีในไฟล์ (เช่น ไฟล์ต้นแบบรุ่นเก่า) = คงค่าเดิม ไม่ล้างเป็นค่าว่าง
                    $data = $row['data'];
                    foreach ($data['_absent'] ?? [] as $field) {
                        $data[$field] = (string) ($current[$field] ?? '');
                    }
                    unset($data['_absent'], $data['_education']);
                    $education = $row['data']['_education'] ?? null;
                    if ($education !== null) {
                        $data['educations'] = self::mergeEducation(PersonnelService::educations((int) $current['id']), $education);
                    }
                    PersonnelService::update($current, $data);
                    $updated++;
                } elseif ($row['action'] === 'create' && $code === '') {
                    $data = $row['data'];
                    if (($data['_education'] ?? null) !== null) {
                        $data['educations'] = [$data['_education']];
                    }
                    unset($data['_absent'], $data['_education']);
                    PersonnelService::create($data);
                    $created++;
                } else {
                    throw new ValidationException(['employee_code' => ['ข้อมูลรหัสบุคลากรเปลี่ยนไประหว่างรอยืนยัน']]);
                }
            } catch (ValidationException | HttpException $e) {
                $message = $e instanceof ValidationException
                    ? implode(', ', array_merge(...array_values($e->errors())))
                    : $e->getMessage();
                Audit::log('employee.import_partial', 'core', 'employees', null, null, [
                    'created' => $created, 'updated' => $updated, 'failed_line' => $row['line'],
                ]);
                throw new ValidationException(['file' => [
                    "บันทึกสำเร็จ {$created} เพิ่ม / {$updated} แก้ไข แล้วหยุดที่บรรทัด {$row['line']}: {$message}",
                ]]);
            }
        }
        Audit::log('employee.imported', 'core', 'employees', null, null, ['created' => $created, 'updated' => $updated]);

        return ['created' => $created, 'updated' => $updated];
    }

    public static function hasPending(): bool
    {
        $pending = Session::get(self::SESSION_KEY);

        return is_array($pending) && (time() - (int) ($pending['created'] ?? 0)) <= self::TTL;
    }

    public static function discard(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    // ------------------------------------------------------------------ ภายใน

    private static function assertUpdatable(array $existing, array $data, bool $updateExisting): string
    {
        if ($existing['deleted_at'] !== null) {
            throw new ValidationException(['employee_code' => ['รหัสนี้เป็นของบุคลากรที่ถูกลบแล้ว']]);
        }
        if (!$updateExisting) {
            throw new ValidationException(['employee_code' => ['มีรหัสบุคลากรนี้แล้ว (เลือก "อัปเดตข้อมูลเดิม" หากต้องการแก้ไข หรือเว้นว่างหากเป็นบุคลากรใหม่)']]);
        }
        $current = EmployeeModel::findDetail((int) $existing['id']);
        if ($current === null || !PersonnelService::canUpdate($current)) {
            throw new ValidationException(['employee_code' => ['คุณไม่มีสิทธิ์แก้ไขบุคลากรรายนี้']]);
        }
        if ($data['status'] !== $current['status'] && !PersonnelService::canManageLinkedAccount($current)) {
            throw new ValidationException(['status' => ['ไม่สามารถเปลี่ยนสถานะของบุคลากรที่มีบัญชีระดับสิทธิ์เท่ากันหรือสูงกว่าได้']]);
        }

        return 'update';
    }

    /** @return list<array<string, string>> */
    private static function parse(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new ValidationException(['file' => ['กรุณาเลือกไฟล์ CSV']]);
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new ValidationException(['file' => ['ไฟล์ต้องมีขนาดไม่เกิน 2 MB']]);
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if ($ext !== 'csv' || !in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true)) {
            throw new ValidationException(['file' => ['รองรับเฉพาะไฟล์ .csv (Excel: บันทึกเป็น "CSV UTF-8")']]);
        }

        $content = (string) file_get_contents((string) $file['tmp_name']);
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $converted = function_exists('iconv') ? @iconv('CP874', 'UTF-8//IGNORE', $content) : false;
            if ($converted === false || !mb_check_encoding($converted, 'UTF-8')) {
                throw new ValidationException(['file' => ['ไม่รู้จักการเข้ารหัสไฟล์ กรุณาบันทึกเป็น "CSV UTF-8"']]);
            }
            $content = $converted;
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $header = fgetcsv($handle, 0, ',', '"', '');
        if (!is_array($header)) {
            throw new ValidationException(['file' => ['ไม่พบหัวคอลัมน์ในไฟล์']]);
        }
        $header = array_map(static fn ($h): string => strtolower(trim((string) $h)), $header);
        $missing = array_diff(['first_name', 'last_name', 'department_code'], $header);
        if ($missing !== []) {
            throw new ValidationException(['file' => ['ขาดคอลัมน์ที่จำเป็น: ' . implode(', ', $missing)]]);
        }

        $records = [];
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($cells === [null] || implode('', array_map('strval', $cells)) === '') {
                continue;
            }
            if (count($records) >= self::MAX_ROWS) {
                throw new ValidationException(['file' => ['นำเข้าได้ครั้งละไม่เกิน ' . self::MAX_ROWS . ' แถว']]);
            }
            $record = [];
            foreach ($header as $i => $column) {
                if (in_array($column, self::COLUMNS, true)) {
                    $record[$column] = trim((string) ($cells[$i] ?? ''));
                }
            }
            $records[] = $record;
        }
        fclose($handle);

        return $records;
    }

    /** แปลงค่าจาก CSV (รับได้ทั้งรหัสภาษาอังกฤษและป้ายภาษาไทย / วันที่ พ.ศ. หรือ ค.ศ.) */
    private static function normalize(array $record, array $departments): array
    {
        $data = [];
        foreach (PersonnelService::FIELDS as $field) {
            $data[$field] = $record[$field] ?? '';
        }
        $data['_absent'] = array_values(array_diff(PersonnelService::FIELDS, array_keys($record), ['department_id', 'service_unit_id']));
        if (!array_key_exists('service_unit_code', $record)) {
            $data['_absent'][] = 'service_unit_id';
        }
        $data['employee_code'] = trim((string) ($record['employee_code'] ?? ''));
        $data['national_id'] = $record['national_id'] ?? '';
        $data['employment_type'] = self::mapLabel($data['employment_type'], UserService::EMPLOYMENT_TYPES, 'civil_servant');
        $data['gender'] = self::mapLabel($data['gender'], PersonnelService::GENDERS, '');
        $data['status'] = self::mapLabel($data['status'], PersonnelService::STATUSES, 'active');

        $dept = mb_strtolower(trim((string) ($record['department_code'] ?? '')));
        $data['department_id'] = $departments[$dept] ?? '';
        if ($dept !== '' && $data['department_id'] === '') {
            throw new ValidationException(['department_id' => ['ไม่พบฝ่าย/งาน "' . $record['department_code'] . '"']]);
        }
        // สังกัดรอง: รหัสหรือชื่อหน่วยบริการ (ว่าง = ไม่มี)
        $unit = mb_strtolower(trim((string) ($record['service_unit_code'] ?? '')));
        $data['service_unit_id'] = $unit !== '' ? ($departments[$unit] ?? null) : '';
        if ($data['service_unit_id'] === null) {
            throw new ValidationException(['service_unit_id' => ['ไม่พบหน่วยบริการ "' . $record['service_unit_code'] . '"']]);
        }
        foreach (['birth_date', 'start_date', 'end_date', 'license_expiry_date', 'appointment_date'] as $field) {
            $data[$field] = self::normalizeDate((string) $data[$field], PersonnelService::LABELS[$field]);
        }
        $data['_education'] = self::education($record);

        return $data;
    }

    /**
     * วุฒิการศึกษาในไฟล์ (education_level + education_major) → ข้อมูลการศึกษา 1 รายการ (null = ไม่มีในไฟล์)
     * ระดับรับได้ทั้งรหัสและชื่อ (เช่น master, ปริญญาโท) ข้อความอื่นบันทึกเป็นระดับ "อื่นๆ" พร้อมข้อความในช่องวุฒิการศึกษา
     */
    private static function education(array $record): ?array
    {
        $text = trim((string) ($record['education_level'] ?? ''));
        if ($text === '') {
            return null;
        }
        $level = self::mapLabel($text, PersonnelService::EDUCATION_LEVELS, 'other');
        $known = isset(PersonnelService::EDUCATION_LEVELS[$level]);
        $rows = PersonnelService::educationInput([[
            'level' => $known ? $level : 'other',
            'degree' => $known ? '' : $text,
            'major' => (string) ($record['education_major'] ?? ''),
        ]]);

        return $rows[0];
    }

    /**
     * รวมวุฒิจากไฟล์เข้ากับข้อมูลการศึกษาเดิม: ระดับที่มีอยู่แล้ว (และวุฒิเดียวกัน) ไม่เพิ่มซ้ำ แต่เติมสาขาวิชาถ้าเดิมว่าง
     * ข้อมูลการศึกษาเดิมที่ไม่อยู่ในไฟล์คงไว้ทั้งหมด
     */
    private static function mergeEducation(array $existing, array $new): array
    {
        $rows = array_map(static fn (array $e): array => [
            'level' => (string) $e['level'], 'institution' => $e['institution'], 'degree' => $e['degree'],
            'major' => $e['major'], 'graduated_year' => $e['graduated_year'] !== null ? (int) $e['graduated_year'] : null,
        ], $existing);
        foreach ($rows as &$row) {
            if ($row['level'] === $new['level'] && ($new['level'] !== 'other' || $row['degree'] === $new['degree'])) {
                $row['major'] ??= $new['major'];

                return $rows;
            }
        }
        unset($row);
        $rows[] = $new;

        return $rows;
    }

    private static function mapLabel(string $value, array $options, string $default): string
    {
        $value = trim($value);
        if ($value === '') {
            return $default;
        }
        if (array_key_exists($value, $options)) {
            return $value;
        }
        $key = array_search($value, $options, true);

        return $key !== false ? (string) $key : $value; // ค่าไม่ถูกต้องปล่อยให้ Validator แจ้ง
    }

    /** รองรับ YYYY-MM-DD และ DD/MM/YYYY ทั้ง พ.ศ. (ปี > 2400) และ ค.ศ. */
    private static function normalizeDate(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            throw new ValidationException(['date' => ["{$label} รูปแบบไม่ถูกต้อง (ใช้ YYYY-MM-DD หรือ DD/MM/YYYY)"]]);
        }
        if ($y > 2400) {
            $y -= 543;
        }
        if (!checkdate($mo, $d, $y)) {
            throw new ValidationException(['date' => ["{$label} ไม่ใช่วันที่ที่มีอยู่จริง"]]);
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /** @return array<string, int> code/ชื่อ (ตัวพิมพ์เล็ก) → id */
    private static function departmentLookup(): array
    {
        $map = [];
        foreach (Database::instance()->fetchAll('SELECT id, code, name_th FROM departments WHERE is_active = 1') as $d) {
            $map[mb_strtolower((string) $d['code'])] = (int) $d['id'];
            $map[mb_strtolower((string) $d['name_th'])] = (int) $d['id'];
        }

        return $map;
    }

    private static function csvLine(array $cells): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $cells, ',', '"', '');
        rewind($out);
        $line = (string) stream_get_contents($out);
        fclose($out);

        return $line;
    }
}
