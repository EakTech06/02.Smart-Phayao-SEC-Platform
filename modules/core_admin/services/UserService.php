<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Auth;
use Core\Crypto;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Password;
use Core\Rbac;
use Modules\CoreAdmin\Models\UserModel;

final class UserService
{
    public const EMPLOYMENT_TYPES = [
        'civil_servant' => 'ข้าราชการ',
        'government_employee' => 'พนักงานราชการ',
        'permanent_employee' => 'ลูกจ้างประจำ',
        'temporary_employee' => 'ลูกจ้างชั่วคราว',
        'other' => 'อื่น ๆ',
    ];

    public const STATUSES = ['active' => 'ใช้งาน', 'inactive' => 'ระงับ', 'locked' => 'ล็อก'];

    private const EMPLOYEE_FIELDS = [
        'employee_code', 'title_name', 'first_name', 'last_name', 'nickname', 'position',
        'academic_rank', 'employment_type', 'department_id', 'phone', 'email', 'start_date',
    ];

    public static function isSuperAdmin(): bool
    {
        return Rbac::hasRole('super_admin');
    }

    /** ระดับ Role สูงสุดของผู้ใช้ (ตัวเลขน้อย = สิทธิ์สูง) ไม่มี Role = 255 */
    public static function levelOf(int $userId): int
    {
        $level = Database::instance()->fetchColumn(
            'SELECT MIN(r.level) FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = ? AND r.is_active = 1',
            [$userId]
        );

        return $level === null ? 255 : (int) $level;
    }

    public static function assignableRoles(): array
    {
        $db = Database::instance();
        if (self::isSuperAdmin()) {
            return $db->fetchAll('SELECT id, code, name_th, level FROM roles WHERE is_active = 1 ORDER BY level');
        }

        return $db->fetchAll(
            'SELECT id, code, name_th, level FROM roles WHERE is_active = 1 AND level > ? ORDER BY level',
            [self::levelOf((int) Auth::id())]
        );
    }

    public static function departmentOptions(): array
    {
        $db = Database::instance();
        if (Rbac::canAny(['core.users.create_all', 'core.users.update_all'])) {
            return $db->fetchAll('SELECT id, name_th FROM departments WHERE is_active = 1 ORDER BY sort_order, id');
        }
        $ids = Rbac::departmentIds();
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));

        return $db->fetchAll(
            "SELECT id, name_th FROM departments WHERE is_active = 1 AND id IN ({$in}) ORDER BY sort_order, id",
            $ids
        );
    }

    /** @return list<int> */
    public static function roleIdsOf(int $userId): array
    {
        $rows = Database::instance()->fetchAll('SELECT role_id FROM user_roles WHERE user_id = ?', [$userId]);

        return array_map('intval', array_column($rows, 'role_id'));
    }

    /** ห้ามจัดการบัญชีตนเอง / ต้องอยู่ใน Data Scope / ต้องมีระดับสูงกว่าเป้าหมาย */
    public static function assertCanManage(array $target): void
    {
        $targetId = (int) $target['id'];
        if ($targetId === Auth::id()) {
            throw new HttpException(403, 'ไม่สามารถจัดการบัญชีของตนเองผ่านเมนูนี้ได้');
        }
        if (self::isSuperAdmin()) {
            return;
        }
        $departmentId = $target['department_id'] !== null ? (int) $target['department_id'] : null;
        if (!Rbac::canAccessRecord('core.users', 'update', $targetId, $departmentId)) {
            throw new HttpException(404);
        }
        if (self::levelOf($targetId) <= self::levelOf((int) Auth::id())) {
            throw new HttpException(403, 'ไม่สามารถจัดการผู้ใช้ที่มีระดับสิทธิ์เท่ากันหรือสูงกว่าได้');
        }
    }

    public static function canManage(array $target): bool
    {
        if (!Rbac::canAny(['core.users.update_all', 'core.users.update_department'])) {
            return false;
        }
        try {
            self::assertCanManage($target);

            return true;
        } catch (HttpException) {
            return false;
        }
    }

    /** @return array{0: int, 1: string} [userId, รหัสผ่านชั่วคราว] */
    public static function create(array $data, array $roleIds): array
    {
        $roleIds = self::filterRoleIds($roleIds);
        $nid = self::nationalId($data['national_id'] ?? null);
        self::assertUnique($data, null, null, $nid['hash']);
        $temp = Password::temporary();

        $userId = Database::instance()->transaction(
            static function (Database $db) use ($data, $roleIds, $nid, $temp): int {
                $employeeId = $db->insert('employees', self::employeeRow($data) + [
                    'national_id_hash' => $nid['hash'],
                    'national_id_last4' => $nid['last4'],
                ]);
                $userId = $db->insert('users', [
                    'employee_id' => $employeeId,
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password_hash' => Password::hash($temp),
                    'status' => 'active',
                    'must_change_password' => 1,
                    'created_by' => Auth::id(),
                ]);
                self::syncRoles($db, $userId, $roleIds);
                self::syncPrimaryDepartment($db, $userId, (int) $data['department_id']);

                return $userId;
            }
        );

        Audit::log('user.created', 'core', 'users', $userId, null, self::snapshot($userId));

        return [$userId, $temp];
    }

    public static function update(array $target, array $data, array $roleIds, string $status): void
    {
        $userId = (int) $target['id'];
        $employeeId = $target['employee_id'] !== null ? (int) $target['employee_id'] : null;
        $roleIds = self::filterRoleIds($roleIds);
        $nid = self::nationalId($data['national_id'] ?? null);
        self::assertUnique($data, $userId, $employeeId, $nid['hash']);
        $before = self::snapshot($userId);

        Database::instance()->transaction(
            static function (Database $db) use ($userId, $employeeId, $data, $roleIds, $nid, $status): void {
                $row = self::employeeRow($data);
                if ($nid['hash'] !== null) {
                    $row['national_id_hash'] = $nid['hash'];
                    $row['national_id_last4'] = $nid['last4'];
                }
                if ($employeeId === null) {
                    $employeeId = $db->insert('employees', $row);
                } else {
                    $db->update('employees', $row, ['id' => $employeeId]);
                }
                $db->update('users', [
                    'employee_id' => $employeeId,
                    'email' => $data['email'],
                    'status' => $status,
                ], ['id' => $userId]);
                self::syncRoles($db, $userId, $roleIds);
                self::syncPrimaryDepartment($db, $userId, (int) $data['department_id']);
            }
        );

        if ($status !== 'active') {
            self::revokeSessions($userId);
        }
        Audit::log('user.updated', 'core', 'users', $userId, $before, self::snapshot($userId));
    }

    public static function setStatus(array $target, string $status): void
    {
        $userId = (int) $target['id'];
        Database::instance()->update('users', ['status' => $status], ['id' => $userId]);
        if ($status !== 'active') {
            self::revokeSessions($userId);
        }
        Audit::log('user.status_changed', 'core', 'users', $userId, ['status' => $target['status']], ['status' => $status]);
    }

    /** รีเซ็ตรหัสผ่าน + ปลดล็อกบัญชี + บังคับออกจากระบบทุกอุปกรณ์ */
    public static function resetPassword(array $target): string
    {
        $userId = (int) $target['id'];
        $temp = Password::temporary();
        $db = Database::instance();
        $db->update('users', ['password_hash' => Password::hash($temp), 'must_change_password' => 1], ['id' => $userId]);
        $db->query('DELETE FROM login_attempts WHERE username = ? AND success = 0', [(string) $target['username']]);
        self::revokeSessions($userId);
        Audit::log('user.password_reset', 'core', 'users', $userId);

        return $temp;
    }

    public static function delete(array $target): void
    {
        $userId = (int) $target['id'];
        Database::instance()->update('users', [
            'deleted_at' => Database::now(),
            'status' => 'inactive',
        ], ['id' => $userId]);
        self::revokeSessions($userId);
        Audit::log('user.deleted', 'core', 'users', $userId, ['username' => $target['username']]);
    }

    public static function revokeSessions(int $userId): void
    {
        Database::instance()->query('DELETE FROM sessions WHERE user_id = ?', [$userId]);
    }

    /** @return list<int> */
    private static function filterRoleIds(array $roleIds): array
    {
        if ($roleIds === []) {
            throw new ValidationException(['roles' => ['กรุณาเลือกบทบาทอย่างน้อย 1 บทบาท']]);
        }
        $assignable = array_map('intval', array_column(self::assignableRoles(), 'id'));
        if (array_diff($roleIds, $assignable) !== []) {
            throw new ValidationException(['roles' => ['มีบทบาทที่คุณไม่มีสิทธิ์กำหนด']]);
        }

        return $roleIds;
    }

    /** ลบเฉพาะ Role ที่ผู้กระทำมีสิทธิ์กำหนด แล้วใส่ใหม่ (Role ระดับสูงกว่าคงไว้) */
    private static function syncRoles(Database $db, int $userId, array $roleIds): void
    {
        $assignable = array_map('intval', array_column(self::assignableRoles(), 'id'));
        if ($assignable !== []) {
            $in = implode(',', array_fill(0, count($assignable), '?'));
            $db->query("DELETE FROM user_roles WHERE user_id = ? AND role_id IN ({$in})", [$userId, ...$assignable]);
        }
        foreach ($roleIds as $roleId) {
            $db->insert('user_roles', ['user_id' => $userId, 'role_id' => $roleId, 'assigned_by' => Auth::id()]);
        }
    }

    private static function syncPrimaryDepartment(Database $db, int $userId, int $departmentId): void
    {
        $db->query(
            'DELETE FROM user_departments WHERE user_id = ? AND is_primary = 1 AND department_id <> ?',
            [$userId, $departmentId]
        );
        $db->query(
            'INSERT INTO user_departments (user_id, department_id, is_primary) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE is_primary = 1',
            [$userId, $departmentId]
        );
    }

    private static function employeeRow(array $data): array
    {
        $row = [];
        foreach (self::EMPLOYEE_FIELDS as $field) {
            $row[$field] = $data[$field] ?? null;
        }
        $row['department_id'] = (int) $data['department_id'];

        return $row;
    }

    /** @return array{hash: ?string, last4: ?string} */
    private static function nationalId(?string $value): array
    {
        $digits = (string) preg_replace('/\D/', '', (string) $value);
        if ($digits === '') {
            return ['hash' => null, 'last4' => null];
        }

        return ['hash' => hash_hmac('sha256', $digits, Crypto::key()), 'last4' => substr($digits, -4)];
    }

    private static function assertUnique(array $data, ?int $userId, ?int $employeeId, ?string $nidHash): void
    {
        $db = Database::instance();
        $errors = [];

        if (isset($data['username'])
            && $db->fetchColumn('SELECT 1 FROM users WHERE username = ? AND id <> ?', [$data['username'], $userId ?? 0]) !== null) {
            $errors['username'][] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
        }
        if ($data['email'] !== null
            && $db->fetchColumn('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$data['email'], $userId ?? 0]) !== null) {
            $errors['email'][] = 'อีเมลนี้ถูกใช้แล้ว';
        }
        if ($db->fetchColumn('SELECT 1 FROM employees WHERE employee_code = ? AND id <> ?', [$data['employee_code'], $employeeId ?? 0]) !== null) {
            $errors['employee_code'][] = 'รหัสบุคลากรนี้ถูกใช้แล้ว';
        }
        if ($nidHash !== null
            && $db->fetchColumn('SELECT 1 FROM employees WHERE national_id_hash = ? AND id <> ?', [$nidHash, $employeeId ?? 0]) !== null) {
            $errors['national_id'][] = 'เลขประจำตัวประชาชนนี้มีในระบบแล้ว';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private static function snapshot(int $userId): array
    {
        $row = UserModel::findForEdit($userId) ?? [];
        unset($row['national_id_last4']);
        $row['role_ids'] = self::roleIdsOf($userId);

        return $row;
    }
}