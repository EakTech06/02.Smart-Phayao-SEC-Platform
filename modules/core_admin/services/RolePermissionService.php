<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Database;
use Core\Exceptions\ValidationException;
use Core\Rbac;

final class RolePermissionService
{
    public static function groupedPermissions(): array
    {
        $rows = Database::instance()->fetchAll('SELECT id, code, module, resource, action, description FROM permissions ORDER BY module, resource, action');
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['module']][$row['resource']][] = $row;
        }
        return $grouped;
    }

    public static function rolePermissionIds(int $roleId): array
    {
        $rows = Database::instance()->fetchAll('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$roleId]);
        return array_map('intval', array_column($rows, 'permission_id'));
    }

    public static function updateMatrix(int $roleId, array $permissionIds): void
    {
        $db = Database::instance();
        $role = $db->fetch('SELECT code FROM roles WHERE id = ?', [$roleId]);
        if ($role === null) {
            throw new \RuntimeException('ไม่พบบทบาทที่ต้องการแก้ไข');
        }
        if ($role['code'] === 'super_admin') {
            throw new ValidationException(['matrix' => ['ไม่สามารถแก้ไขสิทธิ์ของ Super Admin ได้']]);
        }

        $permissionIds = array_unique(array_map('intval', $permissionIds));
        $before = self::rolePermissionIds($roleId);

        $db->transaction(static function (Database $db) use ($roleId, $permissionIds): void {
            $db->delete('role_permissions', ['role_id' => $roleId]);
            foreach ($permissionIds as $permId) {
                $db->insert('role_permissions', [
                    'role_id' => $roleId,
                    'permission_id' => $permId,
                    'created_at' => Database::now(),
                ]);
            }
        });

        Rbac::flush();
        Audit::log('role.permissions_updated', 'core', 'roles', $roleId, ['permission_ids' => $before], ['permission_ids' => $permissionIds]);
    }
}