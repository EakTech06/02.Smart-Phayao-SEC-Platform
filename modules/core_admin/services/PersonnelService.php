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

    /**
     * ฟิลด์ที่บันทึกลง employees จากฟอร์ม/CSV (ไม่รวมเลขบัตรซึ่งจัดการแยก)
     * employee_code ไม่อยู่ในรายการนี้: ระบบสร้างให้อัตโนมัติตอนเพิ่ม และแก้ไขไม่ได้
     */
    public const FIELDS = [
        'title_name', 'first_name', 'last_name', 'nickname', 'gender', 'birth_date',
        'position', 'position_number', 'academic_rank', 'employment_type', 'education_level', 'education_major',
        'department_id', 'phone', 'email', 'address', 'start_date', 'end_date', 'status', 'note',
    ];

    public const LABELS = [
        'employee_code' => 'รหัสบุคลากร', 'title_name' => 'คำนำหน้า', 'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล',
        'nickname' => 'ชื่อเล่น', 'gender' => 'เพศ', 'birth_date' => 'วันเกิด', 'position' => 'ตำแหน่ง',
        'position_number' => 'เลขที่ตำแหน่ง', 'academic_rank' => 'วิทยฐานะ', 'employment_type' => 'ประเภทบุคลากร',
        'education_level' => 'วุฒิการศึกษา', 'education_major' => 'สาขาวิชา', 'department_id' => 'ฝ่าย/งาน',
        'phone' => 'เบอร์โทรศัพท์', 'email' => 'อีเมล', 'address' => 'ที่อยู่', 'start_date' => 'วันที่เริ่มปฏิบัติงาน',
        'end_date' => 'วันที่สิ้นสุดการปฏิบัติงาน', 'status' => 'สถานะ', 'note' => 'หมายเหตุ',
        'national_id' => 'เลขประจำตัวประชาชน',
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
            'department_id' => 'required|integer',
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

    public static function canDelete(array $row): bool
    {
        return Rbac::can('core.employees.delete_all') && self::canManageLinkedAccount($row);
    }

    /** ฝ่าย/งานที่ผู้ใช้เลือกได้: สิทธิ์ระดับ all เลือกได้ทุกฝ่าย, ระดับ department เลือกได้เฉพาะฝ่ายตนเอง */
    public static function departmentOptions(): array
    {
        $db = Database::instance();
        if (Rbac::canAny(['core.employees.create_all', 'core.employees.update_all', 'core.employees.import_all'])) {
            return $db->fetchAll('SELECT id, code, name_th FROM departments WHERE is_active = 1 ORDER BY sort_order, id');
        }
        $ids = Rbac::departmentIds();
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));

        return $db->fetchAll(
            "SELECT id, code, name_th FROM departments WHERE is_active = 1 AND id IN ({$in}) ORDER BY sort_order, id",
            $ids
        );
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
    public static function create(array $data): int
    {
        $nid = self::nationalIdFields($data['national_id'] ?? null);
        self::assertUnique($data, null, $nid['national_id_hash']);

        $id = self::insertWithCode(Database::instance(), self::row($data) + $nid + [
            'created_by' => Auth::id(),
        ]);
        Audit::log('employee.created', 'core', 'employees', $id, null, self::snapshot($id));

        return $id;
    }

    public static function update(array $current, array $data): void
    {
        $id = (int) $current['id'];
        $nid = self::nationalIdFields($data['national_id'] ?? null);
        self::assertUnique($data, $id, $nid['national_id_hash']);

        $statusChanged = $data['status'] !== $current['status'];
        if ($statusChanged && !self::canManageLinkedAccount($current)) {
            throw new HttpException(403, 'ไม่สามารถเปลี่ยนสถานะของบุคลากรที่มีบัญชีระดับสิทธิ์เท่ากันหรือสูงกว่าได้');
        }

        $before = self::snapshot($id);
        $row = self::row($data) + ['updated_by' => Auth::id()];
        if ($nid['national_id_hash'] !== null) {
            $row += $nid; // เว้นว่าง = ไม่เปลี่ยนเลขบัตรเดิม
        }

        Database::instance()->transaction(static function (Database $db) use ($id, $row, $current): void {
            $db->update('employees', $row, ['id' => $id]);
            if ($current['user_id'] !== null) {
                self::syncLinkedUser($db, (int) $current['user_id'], $row);
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

        return $row;
    }
}
