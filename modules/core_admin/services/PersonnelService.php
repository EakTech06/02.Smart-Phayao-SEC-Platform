<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Auth;
use Core\Crypto;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Rbac;
use Core\Setting;
use Modules\CoreAdmin\Models\EmployeeModel;
use Modules\CoreAdmin\Models\UserModel;

/** ข้อมูลบุคลากร (employees) + การซิงก์สถานะไปยังบัญชีผู้ใช้ที่ผูกไว้ 1:1 */
final class PersonnelService
{
    public const STATUSES = [
        'active' => 'ปฏิบัติงาน',
        'inactive' => 'พักงาน/ไม่ปฏิบัติงาน',
        'retired' => 'เกษียณ',
        'resigned' => 'ลาออก/ย้าย',
    ];

    public const GENDERS = ['male' => 'ชาย', 'female' => 'หญิง', 'unspecified' => 'ไม่ระบุ'];

    /** แจ้งเตือนใบประกอบวิชาชีพครูล่วงหน้า (วัน) */
    public const LICENSE_WARN_DAYS = 90;

    /**
     * สถานะใบประกอบวิชาชีพครูจากวันหมดอายุ (null = ไม่มีวันหมดอายุ)
     * @return array{state: 'expired'|'expiring'|'valid', days: int, text: string, class: string}|null
     */
    public static function licenseStatus(?string $expiry): ?array
    {
        if ($expiry === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) !== 1) {
            return null;
        }
        $days = (int) (new \DateTimeImmutable('today'))->diff(new \DateTimeImmutable($expiry))->format('%r%a');
        if ($days < 0) {
            return ['state' => 'expired', 'days' => $days, 'class' => 'text-danger',
                'text' => 'ใบประกอบวิชาชีพครูหมดอายุแล้ว ' . abs($days) . ' วัน กรุณาต่ออายุ'];
        }
        if ($days <= self::LICENSE_WARN_DAYS) {
            return ['state' => 'expiring', 'days' => $days, 'class' => 'text-danger',
                'text' => $days === 0 ? 'ใบประกอบวิชาชีพครูหมดอายุวันนี้' : "ใบประกอบวิชาชีพครูจะหมดอายุในอีก {$days} วัน"];
        }

        return ['state' => 'valid', 'days' => $days, 'class' => 'text-success', 'text' => 'ใบประกอบวิชาชีพครูยังไม่หมดอายุ'];
    }

    /** ระดับการศึกษา (employee_educations.level) */
    public const EDUCATION_LEVELS = [
        'primary' => 'ประถมศึกษา',
        'lower_secondary' => 'มัธยมศึกษาตอนต้น',
        'upper_secondary' => 'มัธยมศึกษาตอนปลาย',
        'graduate_diploma' => 'ประกาศนียบัตรบัณฑิต',
        'bachelor' => 'ปริญญาตรี',
        'master' => 'ปริญญาโท',
        'other' => 'อื่นๆ',
    ];

    /** ต่ำกว่าปริญญาตรี: ไม่มีสาขาวิชา */
    public const EDUCATION_NO_MAJOR = ['primary', 'lower_secondary', 'upper_secondary'];

    /** ลำดับวุฒิ ใช้หาวุฒิสูงสุดเก็บใน employees.education_level (ป.บัณฑิตสูงกว่าปริญญาตรี) */
    private const EDUCATION_RANK = [
        'other' => 0, 'primary' => 1, 'lower_secondary' => 2, 'upper_secondary' => 3,
        'bachelor' => 4, 'graduate_diploma' => 5, 'master' => 6,
    ];

    public const MAX_EDUCATIONS = 20;

    /**
     * ฟิลด์ที่บันทึกลง employees จากฟอร์ม/CSV (ไม่รวมเลขบัตรซึ่งจัดการแยก)
     * employee_code ไม่อยู่ในรายการนี้: ระบบสร้างให้อัตโนมัติตอนเพิ่ม และแก้ไขไม่ได้
     */
    public const FIELDS = [
        'title_name', 'first_name', 'last_name', 'nickname', 'gender', 'birth_date',
        'position', 'position_number', 'academic_rank', 'employment_type', 'screener_reg_no',
        'license_no', 'license_expiry_date', 'appointment_date', 'first_position',
        'department_id', 'service_unit_id', 'phone', 'email', 'address', 'start_date', 'end_date', 'status', 'note',
    ];

    public const LABELS = [
        'employee_code' => 'รหัสบุคลากร', 'title_name' => 'คำนำหน้า', 'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล',
        'nickname' => 'ชื่อเล่น', 'gender' => 'เพศ', 'birth_date' => 'วันเดือนปีเกิด', 'position' => 'ตำแหน่ง (ปัจจุบัน)',
        'position_number' => 'เลขที่ตำแหน่ง', 'academic_rank' => 'วิทยฐานะ', 'employment_type' => 'ประเภทบุคลากร',
        'education_level' => 'วุฒิการศึกษา', 'education_major' => 'สาขาวิชา', 'screener_reg_no' => 'เลขทะเบียนผู้คัดกรองคนพิการ',
        'department_id' => 'สังกัดหลัก (ฝ่าย/งาน)', 'service_unit_id' => 'สังกัดรอง (หน่วยบริการ)',
        'phone' => 'เบอร์โทรศัพท์', 'email' => 'อีเมล', 'address' => 'ที่อยู่', 'start_date' => 'วันที่เริ่มปฏิบัติงาน (ที่แห่งนี้)',
        'end_date' => 'วันที่สิ้นสุดการปฏิบัติงาน', 'status' => 'สถานะ', 'note' => 'หมายเหตุ',
        'national_id' => 'เลขประจำตัวประชาชน',
        'license_no' => 'เลขที่ใบประกอบวิชาชีพครู', 'license_expiry_date' => 'วันหมดอายุใบประกอบวิชาชีพครู',
        'first_position' => 'ตำแหน่งเริ่มต้น (ที่แห่งนี้)', 'appointment_date' => 'วันที่บรรจุเข้ารับราชการ',
    ];

    /** กฎตรวจสอบข้อมูล (ใช้ร่วมกันทั้งฟอร์มและ CSV import) */
    public static function rules(): array
    {
        return [
            'title_name' => 'nullable|max:50',
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'nickname' => 'nullable|max:50',
            'gender' => 'nullable|in:' . implode(',', array_keys(self::GENDERS)),
            'birth_date' => 'nullable|date',
            'position' => 'nullable|max:150',
            'position_number' => 'nullable|max:30',
            'academic_rank' => 'nullable|max:100',
            'employment_type' => 'required|in:' . implode(',', array_keys(UserService::EMPLOYMENT_TYPES)),
            'education_level' => 'nullable|max:100',
            'education_major' => 'nullable|max:150',
            'screener_reg_no' => 'nullable|max:50',
            'license_no' => 'nullable|max:50',
            'license_expiry_date' => 'nullable|date',
            'first_position' => 'nullable|max:150',
            'appointment_date' => 'nullable|date',
            'department_id' => 'required|integer',
            'service_unit_id' => 'nullable|integer',
            'phone' => 'nullable|max:30',
            'email' => 'nullable|email|max:191',
            'address' => 'nullable|max:500',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'required|in:' . implode(',', array_keys(self::STATUSES)),
            'note' => 'nullable|max:500',
            'national_id' => 'nullable|thai_id',
        ];
    }

    public static function displayName(array $row): string
    {
        return trim(($row['title_name'] ?? '') . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }

    // ------------------------------------------------------------------ สิทธิ์

    public static function canCreate(): bool
    {
        return Rbac::can('core.employees.create_all');
    }

    public static function canUpdate(array $row): bool
    {
        return EmployeeModel::canAccess($row, 'update');
    }

    /** ฟิลด์ที่ผู้ใช้แก้ไขข้อมูลของตนเองได้ (+ ข้อมูลการศึกษา) ตำแหน่ง/ฝ่าย/วันที่ปฏิบัติงาน/สถานะ แก้ได้เฉพาะงานบุคคล */
    public const SELF_EDIT_FIELDS = [
        'title_name', 'first_name', 'last_name', 'nickname', 'gender', 'birth_date', 'national_id',
        'phone', 'email', 'address', 'screener_reg_no', 'license_no', 'license_expiry_date',
    ];

    /** แก้ไขรายการนี้ได้เพียงเพราะเป็นข้อมูลของตนเอง (core.employees.update_own) จึงแก้ได้เฉพาะ SELF_EDIT_FIELDS */
    public static function isSelfEditOnly(array $row): bool
    {
        if (Rbac::can('core.employees.update_all')) {
            return false;
        }
        if (Rbac::can('core.employees.update_department') && $row['department_id'] !== null
            && in_array((int) $row['department_id'], Rbac::departmentIds(), true)) {
            return false;
        }

        return $row['user_id'] !== null && (int) $row['user_id'] === Auth::id();
    }

    /** ข้อมูลที่ส่งมาจากฟอร์มของตนเอง: ฟิลด์นอก SELF_EDIT_FIELDS ใช้ค่าเดิมเสมอ (ไม่เชื่อค่าจาก request) */
    public static function selfEditInput(array $current, array $input): array
    {
        foreach (self::FIELDS as $field) {
            if (!in_array($field, self::SELF_EDIT_FIELDS, true)) {
                $input[$field] = $current[$field] === null ? '' : (string) $current[$field];
            }
        }

        return $input;
    }

    public static function canDelete(array $row): bool
    {
        return Rbac::can('core.employees.delete_all') && self::canManageLinkedAccount($row);
    }

    /** ฝ่าย/งานที่ผู้ใช้เลือกได้: สิทธิ์ระดับ all เลือกได้ทุกฝ่าย, ระดับ department เลือกได้เฉพาะฝ่ายตนเอง */
    public static function departmentOptions(): array
    {
        $db = Database::instance();
        // depth: 1 = หน่วยย่อยของหน่วยที่ไม่ใช่ระดับบนสุด (เช่น หน่วยบริการรายอำเภอใต้งานหน่วยบริการ) ใช้จัดเยื้องในตัวเลือก
        $select = 'SELECT d.id, d.code, d.name_th, d.parent_id,
                          CASE WHEN p.id IS NOT NULL AND p.parent_id IS NOT NULL THEN 1 ELSE 0 END AS depth
                   FROM departments d LEFT JOIN departments p ON p.id = d.parent_id
                   WHERE d.is_active = 1 AND NOT (p.type <=> \'service_unit\')'; // สังกัดหลัก: ไม่รวมหน่วยบริการรายหน่วย (ใช้เป็นสังกัดรอง)
        if (Rbac::canAny(['core.employees.create_all', 'core.employees.update_all', 'core.employees.import_all'])) {
            return $db->fetchAll($select . ' ORDER BY d.sort_order, d.id');
        }
        $ids = Rbac::departmentIds();
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));

        return $db->fetchAll($select . " AND d.id IN ({$in}) ORDER BY d.sort_order, d.id", $ids);
    }

    public static function assertDepartmentAllowed(int $departmentId): void
    {
        $allowed = array_map('intval', array_column(self::departmentOptions(), 'id'));
        if (!in_array($departmentId, $allowed, true)) {
            throw new ValidationException(['department_id' => ['คุณไม่มีสิทธิ์เลือกฝ่าย/งานนี้']]);
        }
    }

    /**
     * การเปลี่ยนสถานะ/ลบบุคลากรมีผลกับบัญชีผู้ใช้ จึงต้องผ่านกฎเดียวกับการจัดการบัญชี:
     * ห้ามทำกับตนเอง และห้ามทำกับผู้ที่มีระดับบทบาทเท่ากันหรือสูงกว่า (ยกเว้น Super Admin)
     */
    public static function canManageLinkedAccount(array $row): bool
    {
        if ($row['user_id'] === null) {
            return true;
        }
        $userId = (int) $row['user_id'];
        if ($userId === Auth::id()) {
            return false;
        }
        if (UserService::isSuperAdmin()) {
            return true;
        }

        return UserService::levelOf($userId) > UserService::levelOf((int) Auth::id());
    }

    // ------------------------------------------------------------------ เลขประจำตัวประชาชน

    /** @return array{national_id_hash: ?string, national_id_encrypted: ?string, national_id_last4: ?string} */
    public static function nationalIdFields(?string $value): array
    {
        $digits = (string) preg_replace('/\D/', '', (string) $value);
        if ($digits === '') {
            return ['national_id_hash' => null, 'national_id_encrypted' => null, 'national_id_last4' => null];
        }

        return [
            'national_id_hash' => self::nationalIdHash($digits),
            'national_id_encrypted' => Crypto::encrypt($digits),
            'national_id_last4' => substr($digits, -4),
        ];
    }

    /** Blind Index สำหรับค้นหา/ตรวจซ้ำ (ไม่สามารถย้อนกลับเป็นเลขเต็มได้) */
    public static function nationalIdHash(string $digits): string
    {
        return hash_hmac('sha256', $digits, Crypto::key());
    }

    /** ถอดรหัสเลขบัตรเต็ม: ต้องมีสิทธิ์ read_sensitive และบันทึก Audit ทุกครั้ง */
    public static function revealNationalId(array $row): ?string
    {
        if (!Rbac::can('core.employees.read_sensitive_all')) {
            throw new HttpException(403);
        }
        $cipher = Database::instance()->fetchColumn(
            'SELECT national_id_encrypted FROM employees WHERE id = ?',
            [(int) $row['id']]
        );
        Audit::log('employee.national_id_viewed', 'core', 'employees', (int) $row['id']);

        return is_string($cipher) && $cipher !== '' ? Crypto::decrypt($cipher) : null;
    }

    // ------------------------------------------------------------------ รหัสบุคลากรอัตโนมัติ

    /** คำนำหน้ารหัส (settings: personnel.code_prefix) อนุญาตเฉพาะ A-Z a-z 0-9 _ . - */
    public static function codePrefix(): string
    {
        $prefix = (string) preg_replace('/[^A-Za-z0-9_.\-]/', '', (string) Setting::get('personnel.code_prefix', 'T-'));

        return substr($prefix !== '' ? $prefix : 'T-', 0, 20);
    }

    /** จำนวนหลักของเลขลำดับ (settings: personnel.code_digits, 3–8) */
    public static function codeDigits(): int
    {
        return max(3, min(8, (int) Setting::get('personnel.code_digits', 4)));
    }

    /**
     * รหัสถัดไป = คำนำหน้า + (เลขลำดับสูงสุดที่เคยใช้ + 1) นับรวมบุคลากรที่ถูกลบ เพื่อไม่ให้รหัสถูกนำกลับมาใช้ซ้ำ
     * ใช้แสดงตัวอย่างในฟอร์มได้ แต่รหัสจริงกำหนดตอนบันทึก (insertWithCode)
     */
    public static function nextEmployeeCode(?Database $db = null): string
    {
        $db ??= Database::instance();
        $prefix = self::codePrefix();
        $start = strlen($prefix) + 1;
        $like = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix) . '%';
        $max = (int) $db->fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(employee_code, ?) AS UNSIGNED)) FROM employees
             WHERE employee_code LIKE ? ESCAPE '!' AND SUBSTRING(employee_code, ?) REGEXP '^[0-9]+$'",
            [$start, $like, $start]
        );

        return $prefix . str_pad((string) ($max + 1), self::codeDigits(), '0', STR_PAD_LEFT);
    }

    /**
     * INSERT บุคลากรพร้อมรหัสอัตโนมัติ: ถ้ามีผู้บันทึกพร้อมกันจนรหัสชน (unique key) จะคำนวณรหัสใหม่แล้วลองอีกครั้ง
     * ใช้ได้ทั้งในและนอก transaction (MariaDB ยกเลิกเฉพาะคำสั่งที่ชน ไม่ยกเลิกทั้ง transaction)
     */
    public static function insertWithCode(Database $db, array $row): int
    {
        unset($row['employee_code']);
        for ($attempt = 1; ; $attempt++) {
            try {
                return $db->insert('employees', ['employee_code' => self::nextEmployeeCode($db)] + $row);
            } catch (\PDOException $e) {
                $duplicateCode = ($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'uq_employees_code');
                if (!$duplicateCode || $attempt >= 5) {
                    throw $e;
                }
            }
        }
    }

    // ------------------------------------------------------------------ CRUD

    /** @return int employee id */
    // ------------------------------------------------------------------ สังกัดหลัก/สังกัดรอง

    /** ผู้ที่กำหนดสังกัดรอง (หน่วยบริการ) ได้: งานบุคคลระดับทั้งหน่วยงาน (มีผลต่อขอบเขตสิทธิ์ของบัญชีผู้ใช้) */
    public static function canSetServiceUnit(): bool
    {
        return Rbac::canAny(['core.employees.update_all', 'core.employees.create_all', 'core.employees.import_all']);
    }

    /** ตัวเลือกสังกัดรอง: หน่วยบริการที่เปิดใช้งาน (หน่วยย่อยภายใต้งานหน่วยบริการ) */
    public static function serviceUnitOptions(): array
    {
        return Database::instance()->fetchAll(
            "SELECT d.id, d.code, d.name_th FROM departments d JOIN departments p ON p.id = d.parent_id AND p.type = 'service_unit'
             WHERE d.is_active = 1 ORDER BY d.sort_order, d.id"
        );
    }

    /**
     * สังกัดหลัก 1 ฝ่าย (ไม่ใช่หน่วยบริการรายหน่วย) + สังกัดรอง 1 หน่วยบริการ (ไม่บังคับ)
     * ค่าเดิมที่ไม่ได้เปลี่ยนผ่านเสมอ (เช่น หน่วยที่ปิดการใช้งานไปแล้ว)
     */
    private static function assertAffiliation(array $data, ?array $current): void
    {
        $db = Database::instance();
        $errors = [];
        $primary = (int) $data['department_id'];
        if ($current === null || $primary !== (int) $current['department_id']) {
            $isUnit = $db->fetchColumn(
                "SELECT 1 FROM departments d JOIN departments p ON p.id = d.parent_id AND p.type = 'service_unit' WHERE d.id = ?", [$primary]
            ) !== null;
            if ($isUnit) {
                $errors['department_id'][] = 'สังกัดหลักต้องเป็นฝ่าย/งาน — หน่วยบริการให้เลือกเป็นสังกัดรอง';
            }
        }
        $unit = !empty($data['service_unit_id']) ? (int) $data['service_unit_id'] : null;
        $old = $current !== null && $current['service_unit_id'] !== null ? (int) $current['service_unit_id'] : null;
        if ($unit !== $old) {
            if (!self::canSetServiceUnit()) {
                $errors['service_unit_id'][] = 'คุณไม่มีสิทธิ์เปลี่ยนสังกัดรอง (หน่วยบริการ)';
            } elseif ($unit !== null && !in_array($unit, array_map('intval', array_column(self::serviceUnitOptions(), 'id')), true)) {
                $errors['service_unit_id'][] = 'สังกัดรองต้องเป็นหน่วยบริการที่เปิดใช้งาน';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /** สังกัดรองของบุคลากร → ฝ่ายรองของบัญชีผู้ใช้ที่ผูกไว้ (user_departments.is_primary = 0) */
    public static function syncSecondaryDepartment(Database $db, int $userId, ?int $old, ?int $new): void
    {
        if ($old === $new) {
            return;
        }
        if ($old !== null) {
            $db->query('DELETE FROM user_departments WHERE user_id = ? AND department_id = ? AND is_primary = 0', [$userId, $old]);
        }
        if ($new !== null) {
            $db->query('INSERT IGNORE INTO user_departments (user_id, department_id, is_primary) VALUES (?, ?, 0)', [$userId, $new]);
        }
    }

    // ------------------------------------------------------------------ ข้อมูลการศึกษา (หลายระดับ)

    /** @return list<array> */
    public static function educations(int $employeeId): array
    {
        return Database::instance()->fetchAll(
            'SELECT id, level, institution, degree, major, graduated_year FROM employee_educations
             WHERE employee_id = ? ORDER BY sort_order, id', [$employeeId]
        );
    }

    /**
     * แปลง/ตรวจข้อมูลการศึกษาจากฟอร์ม (educations[i][level|institution|degree|major|graduated_year])
     * แถวที่ว่างทั้งแถวข้ามไป · ต่ำกว่าปริญญาตรีล้างสาขาวิชา · ปีรับทั้ง พ.ศ. และ ค.ศ. (เก็บเป็น พ.ศ.)
     * @return list<array{level: string, institution: ?string, degree: ?string, major: ?string, graduated_year: ?int}>
     */
    public static function educationInput(mixed $raw): array
    {
        $rows = [];
        $errors = [];
        $currentBe = (int) date('Y') + 543;
        foreach (is_array($raw) ? array_values($raw) : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $v = static fn (string $k, int $max): ?string => ($s = trim(mb_substr(is_scalar($item[$k] ?? null) ? (string) $item[$k] : '', 0, $max))) !== '' ? $s : null;
            $row = [
                'level' => $v('level', 30),
                'institution' => $v('institution', 191),
                'degree' => $v('degree', 150),
                'major' => $v('major', 150),
                'graduated_year' => $v('graduated_year', 10),
            ];
            if (array_filter($row, static fn ($x): bool => $x !== null) === []) {
                continue;
            }
            $n = count($rows) + 1;
            $msg = [];
            if ($row['level'] === null || !isset(self::EDUCATION_LEVELS[$row['level']])) {
                $msg[] = 'เลือกระดับการศึกษา';
            } elseif (in_array($row['level'], self::EDUCATION_NO_MAJOR, true)) {
                $row['major'] = null;
            }
            if ($row['graduated_year'] !== null) {
                $year = ctype_digit($row['graduated_year']) ? (int) $row['graduated_year'] : 0;
                if ($year >= 1900 && $year < 2400) {
                    $year += 543; // ค.ศ. → พ.ศ.
                }
                if ($year < 2400 || $year > $currentBe + 1) {
                    $msg[] = 'ปีที่สำเร็จการศึกษาต้องเป็นปี พ.ศ. 4 หลัก (ไม่เกิน ' . ($currentBe + 1) . ')';
                } else {
                    $row['graduated_year'] = $year;
                }
            }
            if ($msg !== []) {
                $errors[] = "รายการที่ {$n}: " . implode(', ', $msg);
            }
            $rows[] = $row;
        }
        if (count($rows) > self::MAX_EDUCATIONS) {
            $errors[] = 'บันทึกได้ไม่เกิน ' . self::MAX_EDUCATIONS . ' รายการ';
        }
        if ($errors !== []) {
            throw new ValidationException(['educations' => $errors]);
        }

        return $rows;
    }

    /** แทนที่ข้อมูลการศึกษาทั้งหมด + เก็บวุฒิสูงสุดไว้ที่ employees.education_level/education_major (ใช้ในรายงาน) */
    private static function saveEducations(Database $db, int $employeeId, array $rows): void
    {
        $db->query('DELETE FROM employee_educations WHERE employee_id = ?', [$employeeId]);
        $highest = null;
        foreach ($rows as $i => $row) {
            $db->insert('employee_educations', ['employee_id' => $employeeId, 'sort_order' => $i] + $row);
            if ($highest === null || self::EDUCATION_RANK[$row['level']] > self::EDUCATION_RANK[$highest['level']]) {
                $highest = $row;
            }
        }
        $db->update('employees', [
            'education_level' => $highest === null ? null
                : ($highest['level'] === 'other' && $highest['degree'] !== null ? $highest['degree'] : self::EDUCATION_LEVELS[$highest['level']]),
            'education_major' => $highest['major'] ?? null,
        ], ['id' => $employeeId]);
    }

    /** ข้อความสรุป 1 รายการ เช่น "ปริญญาโท ศษ.ม. สาขาการบริหารการศึกษา มหาวิทยาลัยพะเยา (2560)" */
    public static function educationText(array $e): string
    {
        $parts = [self::EDUCATION_LEVELS[$e['level']] ?? $e['level']];
        if (!empty($e['degree'])) {
            $parts[] = $e['degree'];
        }
        if (!empty($e['major'])) {
            $parts[] = 'สาขา' . $e['major'];
        }
        if (!empty($e['institution'])) {
            $parts[] = $e['institution'];
        }

        return implode(' ', $parts) . (!empty($e['graduated_year']) ? ' (' . $e['graduated_year'] . ')' : '');
    }

    /** ระยะเวลา "x ปี y เดือน" จากวันที่ถึงวันนี้ (หรือถึงวันที่สิ้นสุด) — ใช้กับอายุ อายุราชการ อายุงาน */
    public static function duration(?string $from, ?string $to = null): ?string
    {
        if ($from === null || $from === '') {
            return null;
        }
        $end = new \DateTimeImmutable($to !== null && $to !== '' ? $to : 'today');
        if ($end > new \DateTimeImmutable('today')) {
            $end = new \DateTimeImmutable('today');
        }
        $diff = (new \DateTimeImmutable($from))->diff($end);

        return $diff->invert ? null : "{$diff->y} ปี {$diff->m} เดือน";
    }

    public static function create(array $data): int
    {
        $nid = self::nationalIdFields($data['national_id'] ?? null);
        self::assertUnique($data, null, $nid['national_id_hash']);
        self::assertAffiliation($data, null);

        $row = self::row($data) + $nid + ['created_by' => Auth::id()];
        $id = Database::instance()->transaction(static function (Database $db) use ($row, $data): int {
            $id = self::insertWithCode($db, $row);
            if (isset($data['educations'])) {
                self::saveEducations($db, $id, $data['educations']);
            }

            return $id;
        });
        Audit::log('employee.created', 'core', 'employees', $id, null, self::snapshot($id));

        return $id;
    }

    public static function update(array $current, array $data): void
    {
        $id = (int) $current['id'];
        $nid = self::nationalIdFields($data['national_id'] ?? null);
        self::assertUnique($data, $id, $nid['national_id_hash']);
        if (!self::canSetServiceUnit()) {
            $data['service_unit_id'] = $current['service_unit_id']; // ช่องถูกปิดในฟอร์ม: คงค่าเดิม
        }
        self::assertAffiliation($data, $current);

        $statusChanged = $data['status'] !== $current['status'];
        if ($statusChanged && !self::canManageLinkedAccount($current)) {
            throw new HttpException(403, 'ไม่สามารถเปลี่ยนสถานะของบุคลากรที่มีบัญชีระดับสิทธิ์เท่ากันหรือสูงกว่าได้');
        }

        $before = self::snapshot($id);
        $row = self::row($data) + ['updated_by' => Auth::id()];
        if ($nid['national_id_hash'] !== null) {
            $row += $nid; // เว้นว่าง = ไม่เปลี่ยนเลขบัตรเดิม
        }

        Database::instance()->transaction(static function (Database $db) use ($id, $row, $current, $data): void {
            $db->update('employees', $row, ['id' => $id]);
            if (isset($data['educations'])) {
                self::saveEducations($db, $id, $data['educations']);
            }
            if ($current['user_id'] !== null) {
                self::syncLinkedUser($db, (int) $current['user_id'], $row);
                self::syncSecondaryDepartment($db, (int) $current['user_id'],
                    $current['service_unit_id'] !== null ? (int) $current['service_unit_id'] : null,
                    $row['service_unit_id'] !== null ? (int) $row['service_unit_id'] : null);
            }
        });

        if ($statusChanged) {
            self::syncAccountStatus($current, (string) $data['status']);
        }
        Audit::log('employee.updated', 'core', 'employees', $id, $before, self::snapshot($id));
    }

    /** Soft delete + ระงับบัญชีที่ผูกไว้ (บัญชีไม่ถูกลบ เพื่อเก็บประวัติการใช้งาน) */
    public static function delete(array $current): void
    {
        if (!self::canDelete($current)) {
            throw new HttpException(403, 'ไม่สามารถลบบุคลากรรายนี้ได้');
        }
        $id = (int) $current['id'];
        Database::instance()->update('employees', [
            'deleted_at' => Database::now(),
            'status' => 'inactive',
            'updated_by' => Auth::id(),
        ], ['id' => $id]);
        self::syncAccountStatus($current, 'inactive');
        Audit::log('employee.deleted', 'core', 'employees', $id, [
            'employee_code' => $current['employee_code'],
            'name' => self::displayName($current),
        ]);
    }

    /**
     * Account Sync: บุคลากรพ้นสภาพ (ไม่ใช่ active) → ระงับบัญชี + บังคับออกจากระบบทุกอุปกรณ์
     * กลับมา active → ไม่เปิดบัญชีอัตโนมัติ (ผู้ดูแลต้องเปิดเองที่หน้าผู้ใช้งาน)
     */
    private static function syncAccountStatus(array $employee, string $newStatus): void
    {
        if ($employee['user_id'] === null || $newStatus === 'active') {
            return;
        }
        $user = UserModel::findForEdit((int) $employee['user_id']);
        if ($user !== null && $user['status'] === 'active') {
            UserService::setStatus($user, 'inactive');
            Audit::log('account.sync_deactivated', 'core', 'users', (int) $user['id'], null, [
                'employee_id' => (int) $employee['id'],
                'employee_status' => $newStatus,
            ]);
        }
    }

    /** อีเมลของบุคลากรคือข้อมูลหลัก: ซิงก์ไปยังบัญชีผู้ใช้ที่ผูกไว้ (ใช้ล็อกอินด้วยอีเมลได้) */
    private static function syncLinkedUser(Database $db, int $userId, array $row): void
    {
        $email = $row['email'] ?? null;
        $db->update('users', ['email' => $email !== null ? mb_strtolower((string) $email) : null], ['id' => $userId]);
        $db->query(
            'DELETE FROM user_departments WHERE user_id = ? AND is_primary = 1 AND department_id <> ?',
            [$userId, (int) $row['department_id']]
        );
        $db->query(
            'INSERT INTO user_departments (user_id, department_id, is_primary) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE is_primary = 1',
            [$userId, (int) $row['department_id']]
        );
    }

    private static function row(array $data): array
    {
        $row = [];
        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? null;
            $row[$field] = $value === '' ? null : $value;
        }
        $row['department_id'] = (int) $data['department_id'];
        $row['service_unit_id'] = !empty($data['service_unit_id']) ? (int) $data['service_unit_id'] : null;
        if ($row['email'] !== null) {
            $row['email'] = mb_strtolower((string) $row['email']);
        }
        if (!empty($row['start_date']) && !empty($row['end_date']) && $row['end_date'] < $row['start_date']) {
            throw new ValidationException(['end_date' => ['วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มปฏิบัติงาน']]);
        }

        return $row;
    }

    public static function assertUnique(array $data, ?int $employeeId, ?string $nidHash): void
    {
        $db = Database::instance();
        $errors = [];
        $id = $employeeId ?? 0;

        if ($nidHash !== null
            && $db->fetchColumn('SELECT 1 FROM employees WHERE national_id_hash = ? AND id <> ?', [$nidHash, $id]) !== null) {
            $errors['national_id'][] = 'เลขประจำตัวประชาชนนี้มีในระบบแล้ว';
        }
        if (!empty($data['email'])) {
            $email = mb_strtolower((string) $data['email']);
            $linkedUserId = (int) ($employeeId !== null
                ? $db->fetchColumn('SELECT id FROM users WHERE employee_id = ?', [$employeeId])
                : 0);
            if ($db->fetchColumn('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $linkedUserId]) !== null) {
                $errors['email'][] = 'อีเมลนี้ถูกใช้กับบัญชีผู้ใช้อื่นแล้ว';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private static function snapshot(int $id): array
    {
        $row = EmployeeModel::findDetail($id) ?? [];
        unset($row['national_id_last4'], $row['has_national_id_encrypted'], $row['last_login_at']);
        $row['educations'] = array_map([self::class, 'educationText'], self::educations($id));

        return $row;
    }
}
