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
        'position', 'position_number', 'academic_rank', 'employment_type', 'education_level', 'education_major',
        'department_code', 'phone', 'email', 'address', 'start_date', 'end_date', 'status', 'note', 'national_id',
    ];

    public static function templateCsv(): string
    {
        $example = [
            'T-0101', 'นาง', 'สมใจ', 'ใจดี', 'ใจ', 'หญิง', '2528-04-12', 'ครู', '12345', 'ครูชำนาญการ',
            'ข้าราชการ', 'ปริญญาโท', 'การศึกษาพิเศษ', 'ACADEMIC', '0812345678', 'somjai@py-sec.ac.th', '',
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
                $data = Validator::validate($data, PersonnelService::rules(), PersonnelService::LABELS);
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

                $existing = EmployeeModel::findByCode((string) $data['employee_code']);
                $action = 'create';
                if ($existing !== null) {
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
                $existing = EmployeeModel::findByCode((string) $row['data']['employee_code']);
                if ($row['action'] === 'update' && $existing !== null) {
                    $current = EmployeeModel::findDetail((int) $existing['id']);
                    if ($current === null || !PersonnelService::canUpdate($current)) {
                        throw new HttpException(403);
                    }
                    PersonnelService::update($current, $row['data']);
                    $updated++;
                } elseif ($row['action'] === 'create' && $existing === null) {
                    PersonnelService::create($row['data']);
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
            throw new ValidationException(['employee_code' => ['มีรหัสบุคลากรนี้แล้ว (เลือก "อัปเดตข้อมูลเดิม" หากต้องการแก้ไข)']]);
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
        $missing = array_diff(['employee_code', 'first_name', 'last_name', 'department_code'], $header);
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
        $data['national_id'] = $record['national_id'] ?? '';
        $data['employment_type'] = self::mapLabel($data['employment_type'], UserService::EMPLOYMENT_TYPES, 'other');
        $data['gender'] = self::mapLabel($data['gender'], PersonnelService::GENDERS, '');
        $data['status'] = self::mapLabel($data['status'], PersonnelService::STATUSES, 'active');

        $dept = mb_strtolower(trim((string) ($record['department_code'] ?? '')));
        $data['department_id'] = $departments[$dept] ?? '';
        if ($dept !== '' && $data['department_id'] === '') {
            throw new ValidationException(['department_id' => ['ไม่พบฝ่าย/งาน "' . $record['department_code'] . '"']]);
        }
        foreach (['birth_date', 'start_date', 'end_date'] as $field) {
            $data[$field] = self::normalizeDate((string) $data[$field], PersonnelService::LABELS[$field]);
        }

        return $data;
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
