<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Models;

use Core\Model;
use Core\Rbac;

final class UserModel extends Model
{
    protected static string $table = 'users';
    protected static bool $softDelete = true;

    private const SELECT = "SELECT u.id, u.username, u.email, u.status, u.must_change_password, u.last_login_at, u.created_at,
            e.employee_code, e.title_name, e.first_name, e.last_name, e.position, e.employment_type,
            e.department_id, d.name_th AS department_name,
            (SELECT GROUP_CONCAT(r.name_th ORDER BY r.level SEPARATOR ', ')
               FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS role_names
        FROM users u
        LEFT JOIN employees e ON e.id = u.employee_id
        LEFT JOIN departments d ON d.id = e.department_id";

    public static function listVisible(): array
    {
        [$where, $params] = Rbac::scopeCondition('core.users', 'read', 'e.department_id', 'u.id');

        return static::db()->fetchAll(self::SELECT . " WHERE u.deleted_at IS NULL AND {$where} ORDER BY u.id", $params);
    }

    public static function countVisible(): int
    {
        [$where, $params] = Rbac::scopeCondition('core.users', 'read', 'e.department_id', 'u.id');

        return (int) static::db()->fetchColumn(
            "SELECT COUNT(*) FROM users u LEFT JOIN employees e ON e.id = u.employee_id
             WHERE u.deleted_at IS NULL AND {$where}",
            $params
        );
    }

    public static function findDetail(int $id): ?array
    {
        return static::db()->fetch(self::SELECT . ' WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1', [$id]);
    }

    /** ข้อมูลครบสำหรับฟอร์มแก้ไข (ไม่มี hash ของเลขบัตร) */
    public static function findForEdit(int $id): ?array
    {
        return static::db()->fetch(
            'SELECT u.id, u.username, u.email, u.status, u.employee_id,
                    e.employee_code, e.title_name, e.first_name, e.last_name, e.nickname, e.position,
                    e.academic_rank, e.employment_type, e.department_id, e.phone, e.start_date, e.national_id_last4
             FROM users u LEFT JOIN employees e ON e.id = u.employee_id
             WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1',
            [$id]
        );
    }

    /** ป้องกัน IDOR: คืน null ทั้งกรณีไม่พบและไม่มีสิทธิ์ */
    public static function findVisible(int $id): ?array
    {
        $user = self::findDetail($id);
        if ($user === null) {
            return null;
        }
        $departmentId = $user['department_id'] !== null ? (int) $user['department_id'] : null;

        return Rbac::canAccessRecord('core.users', 'read', (int) $user['id'], $departmentId) ? $user : null;
    }

    public static function toPublic(array $user): array
    {
        return array_intersect_key($user, array_flip([
            'id', 'username', 'email', 'status', 'last_login_at', 'employee_code', 'title_name',
            'first_name', 'last_name', 'position', 'employment_type', 'department_id', 'department_name', 'role_names',
        ]));
    }
}