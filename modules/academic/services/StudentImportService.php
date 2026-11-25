<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Audit;
use Core\Crypto;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Rbac;
use Core\Session;
use Core\Validator;

/**
 * นำเข้าทะเบียนนักเรียนจาก CSV แบบ 2 ขั้น (รูปแบบเดียวกับนำเข้าบุคลากร)
 *   1. อัปโหลด → แปลงค่า → ทดลองบันทึกทุกแถวด้วย StudentService จริงภายใน transaction แล้ว rollback
 *      (ตรวจเลขซ้ำ ทั้งกับฐานข้อมูลและระหว่างแถวในไฟล์ ด้วยกฎชุดเดียวกับฟอร์ม)
 *   2. ยืนยัน → บันทึกทั้งไฟล์ใน transaction เดียว (ผิดแถวใดแถวหนึ่ง = ไม่บันทึกเลย)
 * ไฟล์ไม่ถูกเก็บบนโฮสต์ ข้อมูลรอยืนยัน (มีเลขบัตร) เก็บใน Session แบบเข้ารหัส AES-256-GCM
 * ไม่รับข้อมูลความพิการ/สุขภาพทาง CSV (ข้อมูลอ่อนไหว ให้บันทึกผ่านฟอร์มที่ตรวจสิทธิ์แยก)
 */
final class StudentImportService
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_ROWS = 1000;
    private const SESSION_KEY = '_student_import';
    private const TTL = 900;

    /** คอลัมน์การรับบริการ (เฉพาะนักเรียนใหม่) => ชื่อภาษาไทย */
    public const ENROLL_COLUMNS = [
        'enroll_year' => 'ปีการศึกษา (พ.ศ.)',
        'enroll_service_type' => 'ประเภทการรับบริการ',
        'enroll_unit' => 'หน่วยบริการ (รหัสหรือชื่อ)',
        'enroll_room_code' => 'รหัสห้องบริการ',
        'enroll_grade_level' => 'ระดับชั้น',
        'enroll_teacher_code' => 'รหัสบุคลากรครูผู้รับผิดชอบ',
        'enroll_note' => 'หมายเหตุการรับบริการ',
    ];

    private const DATE_FIELDS = ['birth_date', 'applied_at', 'admitted_at', 'father_birth_date', 'mother_birth_date', 'guardian_birth_date'];

    /** @return list<string> หัวคอลัมน์ของไฟล์ต้นแบบ ตามลำดับฟอร์ม */
    public static function columns(): array
    {
        $cols = ['student_code', 'id_type', 'national_id', 'g_code', 'title_name', 'first_name', 'last_name', 'nickname',
            'first_name_en', 'last_name_en', 'gender', 'birth_date', 'nationality', 'ethnicity', 'religion', 'tribe', 'phone',
            'status', 'applied_at', 'admitted_at', 'note'];
        foreach (['reg', 'cur'] as $p) {
            foreach (array_keys(StudentLookup::ADDRESS_FIELDS) as $k) {
                if (!($p === 'cur' && $k === 'house_code')) {
                    $cols[] = "{$p}_{$k}";
                }
            }
        }
        array_push($cols, ...array_keys(StudentLookup::TRAVEL_FIELDS));
        array_push($cols, 'parents_status', 'lives_with', 'primary_contact');
        foreach (array_keys(StudentLookup::RELATIONS) as $rel) {
            foreach (['title_name', 'first_name', 'last_name', 'birth_date', 'national_id', 'occupation', 'income_per_year', 'phone', 'line_id', 'life_status'] as $f) {
                $cols[] = "{$rel}_{$f}";
            }
        }
        $cols[] = 'guardian_relation_note';
        array_push($cols, ...array_keys(StudentLookup::SIBLING_FIELDS));
        array_push($cols, 'scout_level', 'scout_group', 'redcross_status');
        array_push($cols, ...array_keys(self::ENROLL_COLUMNS));

        return $cols;
    }

    /** @return array<string,string> คอลัมน์ => ชื่อภาษาไทย */
    public static function columnLabels(): array
    {
        $labels = StudentService::labels() + self::ENROLL_COLUMNS;
        $out = [];
        foreach (self::columns() as $c) {
            $out[$c] = $labels[$c] ?? $c;
        }

        return $out;
    }

    /** ค่าที่ใช้ได้ของคอลัมน์แบบตัวเลือก (แสดงในหน้าคำอธิบาย) */
    public static function choices(): array
    {
        $first = static fn (array $list): array => array_map(static fn ($v): string => is_array($v) ? (string) $v[0] : (string) $v, $list);

        return [
            'id_type' => StudentLookup::ID_TYPES,
            'gender' => StudentLookup::GENDERS,
            'status' => array_intersect_key($first(StudentLookup::STATUSES), array_flip(StudentLookup::INITIAL_STATUSES)),
            'parents_status' => StudentLookup::PARENTS_STATUSES,
            'lives_with' => StudentLookup::LIVES_WITH,
            'primary_contact' => StudentLookup::RELATIONS,
            '*_life_status' => StudentLookup::LIFE_STATUSES,
            'scout_level' => StudentLookup::SCOUT_LEVELS,
            'scout_group' => StudentLookup::SCOUT_GROUPS,
            'redcross_status' => StudentLookup::REDCROSS_STATUSES,
            'enroll_service_type' => $first(StudentLookup::SERVICE_TYPES),
        ];
    }

    public static function templateCsv(): string
    {
        $example = array_fill_keys(self::columns(), '');
        $example = array_merge($example, [
            'id_type' => 'citizen', 'title_name' => 'เด็กชาย', 'first_name' => 'ตัวอย่าง', 'last_name' => 'นำเข้าข้อมูล',
            'nickname' => 'ต้น', 'first_name_en' => 'Tuayang', 'last_name_en' => 'Namkhao', 'gender' => 'ชาย',
            'birth_date' => '15/03/2563', 'nationality' => 'ไทย', 'ethnicity' => 'ไทย', 'religion' => 'พุทธ',
            'status' => 'กำลังรับบริการ', 'admitted_at' => '16/05/2568',
            'reg_house_no' => '99', 'reg_moo' => '3', 'reg_subdistrict' => 'บ้านต๋อม', 'reg_district' => 'เมืองพะเยา',
            'reg_province' => 'พะเยา', 'reg_postcode' => '56000', 'travel_km_paved' => '5',
            'parents_status' => 'อยู่ร่วมกัน', 'lives_with' => 'บิดา-มารดา', 'primary_contact' => 'มารดา',
            'mother_title_name' => 'นาง', 'mother_first_name' => 'สมศรี', 'mother_last_name' => 'นำเข้าข้อมูล',
            'mother_occupation' => 'รับจ้าง', 'mother_phone' => '0812345678',
            'child_order' => '1', 'scout_level' => '00', 'scout_group' => '9', 'redcross_status' => '9',
            'enroll_year' => '', 'enroll_service_type' => 'ไป-กลับ (ในศูนย์)', 'enroll_grade_level' => 'เตรียมความพร้อม',
        ]);

        return "\xEF\xBB\xBF" . self::csvLine(self::columns()) . self::csvLine(array_values($example));
    }

    /**
     * @return array{rows: list<array>, errors: array<int, list<string>>, creates: int, updates: int, columns: list<string>}
     */
    public static function preview(array $file, bool $updateExisting): array
    {
        self::authorize($updateExisting);

        return self::previewContent(self::readUpload($file), $updateExisting);
    }

    /** ตรวจเนื้อหา CSV (แยกจากการอัปโหลด เพื่อทดสอบได้) */
    public static function previewContent(string $content, bool $updateExisting): array
    {
        self::authorize($updateExisting);
        [$records, $columns] = self::parse($content);
        if ($records === []) {
            throw new ValidationException(['file' => ['ไม่พบข้อมูลในไฟล์ (มีเฉพาะหัวคอลัมน์)']]);
        }

        $rows = [];
        $errors = [];
        $db = Database::instance();
        try {
            $db->transaction(static function (Database $db) use ($records, $updateExisting, &$rows, &$errors): void {
                foreach ($records as $i => $record) {
                    $line = $i + 2; // บรรทัดที่ 1 คือหัวคอลัมน์
                    try {
                        // savepoint ต่อแถว: แถวที่ผิดไม่ทิ้งข้อมูลของแถวก่อนหน้า จึงตรวจเลขซ้ำระหว่างแถวได้
                        $rows[] = ['line' => $line] + $db->transaction(static fn (): array => self::apply($record, $updateExisting));
                    } catch (ValidationException $e) {
                        $errors[$line] = array_values(array_unique(array_merge(...array_values($e->errors()))));
                    } catch (HttpException $e) {
                        $errors[$line] = ['ไม่มีสิทธิ์บันทึกนักเรียนรายนี้'];
                    }
                }
                throw new DryRunRollback(); // ทดลองบันทึกเท่านั้น
            });
        } catch (DryRunRollback) {
            // rollback แล้ว
        }

        if ($errors === []) {
            Session::set(self::SESSION_KEY, [
                'payload' => Crypto::encrypt(json_encode(['records' => $records, 'update' => $updateExisting], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'created' => time(),
            ]);
        } else {
            Session::forget(self::SESSION_KEY);
        }
        $creates = count(array_filter($rows, static fn (array $r): bool => $r['action'] === 'create'));

        return ['rows' => $rows, 'errors' => $errors, 'creates' => $creates, 'updates' => count($rows) - $creates, 'columns' => $columns];
    }

    /** @return array{created:int, updated:int} */
    public static function commit(): array
    {
        $pending = Session::get(self::SESSION_KEY);
        Session::forget(self::SESSION_KEY);
        if (!is_array($pending) || (time() - (int) ($pending['created'] ?? 0)) > self::TTL) {
            throw new ValidationException(['file' => ['ข้อมูลรอยืนยันหมดอายุ กรุณาอัปโหลดไฟล์ใหม่']]);
        }
        $payload = json_decode(Crypto::decrypt((string) $pending['payload']), true, 512, JSON_THROW_ON_ERROR);
        $update = (bool) $payload['update'];
        self::authorize($update);

        $result = Database::instance()->transaction(static function () use ($payload, $update): array {
            $created = 0;
            $updated = 0;
            foreach ($payload['records'] as $i => $record) {
                try {
                    self::apply($record, $update)['action'] === 'create' ? $created++ : $updated++;
                } catch (ValidationException | HttpException $e) {
                    // ข้อมูลอาจเปลี่ยนระหว่างรอยืนยัน: ยกเลิกทั้งไฟล์
                    $message = $e instanceof ValidationException ? implode(', ', array_merge(...array_values($e->errors()))) : 'ไม่มีสิทธิ์';
                    throw new ValidationException(['file' => ['ไม่ได้บันทึกข้อมูล: บรรทัด ' . ($i + 2) . " ไม่ผ่านการตรวจสอบ ({$message}) กรุณาอัปโหลดใหม่"]]);
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });
        Audit::log('academic.students.imported', 'academic', 'academic_students', null, null, $result);

        return $result;
    }

    public static function discard(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public static function canImport(): bool
    {
        return Rbac::can('academic.students.create_all');
    }

    // ================================================================ ภายใน

    private static function authorize(bool $update): void
    {
        if (!self::canImport() || ($update && !Rbac::can('academic.students.update_all'))) {
            throw new HttpException(403);
        }
    }

    /**
     * บันทึก 1 แถวด้วยบริการเดียวกับฟอร์ม
     * @return array{action:string, student_code:string, name:string, id_masked:string, enrollment:?string, warnings:list<string>}
     */
    private static function apply(array $record, bool $updateExisting): array
    {
        $code = trim((string) ($record['student_code'] ?? ''));
        $current = null;
        if ($code !== '') {
            $existingId = Database::instance()->fetchColumn('SELECT id FROM academic_students WHERE student_code = ? AND deleted_at IS NULL', [$code]);
            if ($existingId !== null) {
                if (!$updateExisting) {
                    throw new ValidationException(['student_code' => ["มีเลขประจำตัวนักเรียน {$code} แล้ว (เลือก \"อัปเดตข้อมูลเดิม\" หากต้องการแก้ไข)"]]);
                }
                $current = StudentService::findVisible((int) $existingId) ?? throw new HttpException(403);
            }
        }

        $input = self::normalize($record, $current);
        if ($current === null) {
            $data = Validator::validate($input, StudentService::rules(true, $input), StudentService::labels());
            $id = StudentService::create($data, true);
            $action = 'create';
        } else {
            $data = Validator::validate($input, StudentService::rules(false, $input), StudentService::labels());
            StudentService::update($current, $data);
            $id = (int) $current['id'];
            $action = 'update';
        }

        $saved = Database::instance()->fetch('SELECT * FROM academic_students WHERE id = ?', [$id]) ?? [];
        $saved['address_registered'] = json_decode((string) ($saved['address_registered'] ?? ''), true);
        $enrollment = null;
        if ($action === 'create' && !empty($data['enroll_service_type'])) {
            $year = Database::instance()->fetchColumn('SELECT name FROM academic_years WHERE id = ?', [(int) $data['enroll_academic_year_id']]);
            $enrollment = StudentLookup::serviceLabel((string) $data['enroll_service_type']) . ' · ' . $year;
        }

        return [
            'action' => $action,
            'student_code' => (string) ($saved['student_code'] ?? ''),
            'name' => StudentService::displayName($saved),
            'id_masked' => match ($saved['id_type'] ?? '') {
                'g_code' => (string) $saved['g_code'],
                default => !empty($saved['national_id_last4']) ? '*********' . $saved['national_id_last4'] : '-',
            },
            'enrollment' => $enrollment,
            'warnings' => StudentCompleteness::warnings($saved, false),
        ];
    }

    /** แปลงค่าจาก CSV เป็นรูปแบบข้อมูลของฟอร์ม; กรณีแก้ไข ช่องที่ว่าง/ไม่มีในไฟล์ = คงค่าเดิม */
    private static function normalize(array $record, ?array $current): array
    {
        $record = array_filter($record, static fn ($v): bool => $v !== '');
        $base = $current !== null ? self::currentInput($current) : ['cur_same' => '1'];
        if ($current === null && array_filter(array_intersect_key($record, array_flip(self::curColumns()))) !== []) {
            $base['cur_same'] = null;
        } elseif ($current !== null && array_intersect_key($record, array_flip(self::curColumns())) !== []) {
            $base['cur_same'] = null; // ไฟล์ระบุที่อยู่ปัจจุบัน = ไม่ใช่ที่เดียวกับทะเบียนบ้าน
        }
        $data = array_merge($base, array_diff_key($record, self::ENROLL_COLUMNS));

        // ค่าตัวเลือก: รับได้ทั้งรหัสและชื่อภาษาไทย
        $choices = self::choices();
        foreach (['gender', 'status', 'parents_status', 'lives_with', 'primary_contact', 'scout_group', 'redcross_status'] as $k) {
            if (isset($record[$k])) {
                $data[$k] = self::mapChoice((string) $record[$k], $choices[$k]);
            }
        }
        if (isset($record['scout_level'])) {
            // Excel ตัดเลข 0 นำหน้า: "0" → "00", "1" → "01"
            $v = ctype_digit((string) $record['scout_level']) ? str_pad((string) $record['scout_level'], 2, '0', STR_PAD_LEFT) : (string) $record['scout_level'];
            $data['scout_level'] = self::mapChoice($v, StudentLookup::SCOUT_LEVELS);
        }
        foreach (array_keys(StudentLookup::RELATIONS) as $rel) {
            if (isset($record["{$rel}_life_status"])) {
                $data["{$rel}_life_status"] = self::mapChoice((string) $record["{$rel}_life_status"], StudentLookup::LIFE_STATUSES);
            }
            if (isset($record["{$rel}_national_id"])) {
                $data["{$rel}_national_id"] = self::digits((string) $record["{$rel}_national_id"], 'เลขประจำตัวประชาชน' . StudentLookup::RELATIONS[$rel]);
            }
        }

        // เอกสารแสดงตน: ว่าง = เดาจากช่องที่กรอก
        if (isset($record['national_id'])) {
            $data['national_id'] = self::digits((string) $record['national_id'], 'เลขประจำตัวประชาชน', true);
        }
        if (isset($record['id_type'])) {
            $short = ['บัตรประชาชน' => 'citizen', 'เลข g' => 'g_code', 'g' => 'g_code', 'หนังสือเดินทาง' => 'passport', 'ไม่มี' => 'none'];
            $data['id_type'] = $short[mb_strtolower((string) $record['id_type'])] ?? self::mapChoice((string) $record['id_type'], StudentLookup::ID_TYPES);
        } elseif ($current === null) {
            $data['id_type'] = isset($record['g_code']) ? 'g_code' : (isset($record['national_id']) ? 'citizen' : 'none');
        }
        $data['status'] ??= 'active';
        if ($current === null && ($data['primary_contact'] ?? null) === null) {
            foreach (['guardian', 'mother', 'father'] as $rel) {
                if (!empty($data["{$rel}_first_name"])) {
                    $data['primary_contact'] = $rel; // ผู้ติดต่อหลักเริ่มต้น
                    break;
                }
            }
        }
        foreach (self::DATE_FIELDS as $f) {
            if (isset($record[$f])) {
                $data[$f] = self::date((string) $record[$f], StudentService::labels()[$f] ?? $f);
            }
        }
        if ($current === null) {
            $data += self::enrollment($record);
        }

        return $data;
    }

    /** คอลัมน์การรับบริการ → enroll_* ของฟอร์ม (ปีว่าง = ปีการศึกษาปัจจุบัน) */
    private static function enrollment(array $r): array
    {
        if (array_intersect_key($r, self::ENROLL_COLUMNS) === []) {
            return [];
        }
        $db = Database::instance();
        $errors = [];
        $type = self::mapChoice((string) ($r['enroll_service_type'] ?? ''), self::choices()['enroll_service_type']);
        if (!isset(StudentLookup::SERVICE_TYPES[$type])) {
            $errors['enroll_service_type'][] = 'กรุณาระบุประเภทการรับบริการเมื่อกรอกข้อมูลการรับบริการ';
        }
        $yearId = isset($r['enroll_year'])
            ? $db->fetchColumn('SELECT id FROM academic_years WHERE year_be = ?', [(int) $r['enroll_year']])
            : StudentService::currentYearId();
        if ($yearId === null) {
            $errors['enroll_academic_year_id'][] = isset($r['enroll_year']) ? "ไม่พบปีการศึกษา {$r['enroll_year']}" : 'ยังไม่ได้ตั้งปีการศึกษาปัจจุบัน';
        }
        $unitId = null;
        if (isset($r['enroll_unit'])) {
            $unitId = $db->fetchColumn('SELECT id FROM departments WHERE (code = ? OR name_th = ?) AND is_active = 1', [$r['enroll_unit'], $r['enroll_unit']]);
            if ($unitId === null) {
                $errors['enroll_department_id'][] = "ไม่พบหน่วยบริการ \"{$r['enroll_unit']}\"";
            }
        }
        $roomId = null;
        if (isset($r['enroll_room_code']) && $yearId !== null) {
            $roomId = $db->fetchColumn('SELECT id FROM service_rooms WHERE room_code = ? AND academic_year_id = ?', [$r['enroll_room_code'], $yearId]);
            if ($roomId === null) {
                $errors['enroll_service_room_id'][] = "ไม่พบห้องบริการรหัส {$r['enroll_room_code']} ในปีการศึกษาที่ระบุ";
            }
        }
        $teacherId = null;
        if (isset($r['enroll_teacher_code'])) {
            $teacherId = $db->fetchColumn('SELECT id FROM employees WHERE employee_code = ? AND deleted_at IS NULL', [$r['enroll_teacher_code']]);
            if ($teacherId === null) {
                $errors['enroll_responsible_employee_id'][] = "ไม่พบรหัสบุคลากร {$r['enroll_teacher_code']}";
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'enroll_academic_year_id' => (string) $yearId, 'enroll_service_type' => $type,
            'enroll_department_id' => $unitId !== null ? (string) $unitId : '', 'enroll_service_room_id' => $roomId !== null ? (string) $roomId : '',
            'enroll_grade_level' => (string) ($r['enroll_grade_level'] ?? ''), 'enroll_responsible_employee_id' => $teacherId !== null ? (string) $teacherId : '',
            'enroll_note' => (string) ($r['enroll_note'] ?? ''),
        ];
    }

    /** ข้อมูลเดิมในรูปแบบช่องของฟอร์ม (ใช้เป็นฐานเมื่ออัปเดต) */
    private static function currentInput(array $student): array
    {
        $out = [];
        foreach ($student as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out[$k] = $v === null ? null : (string) $v;
            }
        }
        foreach ((array) ($student['address_registered'] ?? []) as $k => $v) {
            $out['reg_' . $k] = (string) $v;
        }
        foreach ((array) ($student['address_current'] ?? []) as $k => $v) {
            $out['cur_' . $k] = (string) $v;
        }
        $out['cur_same'] = $student['address_current'] === null ? '1' : null;
        $out['national_id'] = null; // ว่าง = คงเลขเดิม
        foreach (StudentService::guardians((int) $student['id']) as $rel => $g) {
            foreach ($g as $k => $v) {
                if ($k !== 'national_id_last4') {
                    $out["{$rel}_{$k}"] = $v === null ? null : (string) $v;
                }
            }
            if ((int) $g['is_primary_contact'] === 1) {
                $out['primary_contact'] = $rel;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function curColumns(): array
    {
        return array_values(array_filter(self::columns(), static fn (string $c): bool => str_starts_with($c, 'cur_')));
    }

    private static function mapChoice(string $value, array $options): string
    {
        $value = trim($value);
        if ($value === '' || array_key_exists($value, $options)) {
            return $value;
        }
        foreach ($options as $key => $label) {
            $label = is_array($label) ? (string) $label[0] : (string) $label;
            if (mb_strtolower($label) === mb_strtolower($value)) {
                return (string) $key;
            }
        }

        return $value; // ค่าที่ไม่รู้จักปล่อยให้ Validator แจ้ง
    }

    /** เลขบัตร: ตัดขีด/ช่องว่าง และดักค่าที่ Excel แปลงเป็น 1.1E+12 */
    private static function digits(string $value, string $label, bool $allowLetters = false): string
    {
        if (preg_match('/^\d+(\.\d+)?E\+\d+$/i', trim($value))) {
            throw new ValidationException(['national_id' => ["{$label} ถูก Excel แปลงเป็นเลขวิทยาศาสตร์ (เช่น 1.1E+12) ให้ตั้งคอลัมน์เป็น \"ข้อความ\" แล้วบันทึกใหม่"]]);
        }

        return $allowLetters && preg_match('/[A-Za-z]/', $value) ? trim($value) : (string) preg_replace('/[\s\-]/', '', $value);
    }

    /** รองรับ YYYY-MM-DD และ DD/MM/YYYY ทั้ง พ.ศ. (ปี > 2400) และ ค.ศ. */
    private static function date(string $value, string $label): string
    {
        $value = trim($value);
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            throw new ValidationException(['date' => ["{$label} รูปแบบไม่ถูกต้อง (ใช้ 16/05/2568 หรือ 2568-05-16)"]]);
        }
        if ($y > 2400) {
            $y -= 543;
        }
        if (!checkdate($mo, $d, $y)) {
            throw new ValidationException(['date' => ["{$label} ไม่ใช่วันที่ที่มีอยู่จริง"]]);
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /** ตรวจไฟล์ที่อัปโหลด แล้วคืนเนื้อหา (ไฟล์อยู่ใน tmp ของ PHP เท่านั้น ไม่ย้ายไปเก็บ) */
    private static function readUpload(array $file): string
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

        return (string) file_get_contents((string) $file['tmp_name']);
    }

    /**
     * หัวคอลัมน์ใช้ชื่ออังกฤษตามไฟล์ต้นแบบ หรือชื่อภาษาไทยตามหน้าคำอธิบายก็ได้
     * @return array{0: list<array<string,string>>, 1: list<string>}
     */
    private static function parse(string $content): array
    {
        if (strlen($content) > self::MAX_BYTES) {
            throw new ValidationException(['file' => ['ไฟล์ต้องมีขนาดไม่เกิน 2 MB']]);
        }
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
        $aliases = [];
        foreach (self::columnLabels() as $key => $label) {
            $aliases[mb_strtolower($key)] = $key;
            $aliases[mb_strtolower($label)] = $key;
        }
        $map = [];
        $unknown = [];
        foreach ($header as $i => $h) {
            $h = mb_strtolower(trim((string) $h));
            if ($h === '') {
                continue;
            }
            isset($aliases[$h]) ? $map[$i] = $aliases[$h] : $unknown[] = $h;
        }
        $missing = array_diff(['first_name', 'last_name'], $map);
        if ($missing !== []) {
            throw new ValidationException(['file' => ['ขาดคอลัมน์ที่จำเป็น: ' . implode(', ', $missing)
                . ($unknown !== [] ? ' (คอลัมน์ที่ไม่รู้จัก: ' . implode(', ', array_slice($unknown, 0, 10)) . ')' : '')]]);
        }

        $records = [];
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($cells === [null] || trim(implode('', array_map('strval', $cells))) === '') {
                continue;
            }
            if (count($records) >= self::MAX_ROWS) {
                throw new ValidationException(['file' => ['นำเข้าได้ครั้งละไม่เกิน ' . self::MAX_ROWS . ' แถว']]);
            }
            $record = [];
            foreach ($map as $i => $column) {
                $record[$column] = trim((string) ($cells[$i] ?? ''));
            }
            $records[] = $record;
        }
        fclose($handle);

        return [$records, array_values($map)];
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

/** ใช้ยกเลิก transaction ของการทดลองบันทึก (preview) */
final class DryRunRollback extends \RuntimeException
{
}
