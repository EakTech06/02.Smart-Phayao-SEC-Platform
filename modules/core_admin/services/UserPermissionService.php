<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Auth;
use Core\Database;
use Core\Rbac;

final class UserPermissionService
{
    public static function overrides(int $userId): array
    {
        return Database::instance()->fetchAll(
            'SELECT up.*, p.code, p.description FROM user_permissions up
             JOIN permissions p ON p.id = up.permission_id
             WHERE up.user_id = ? ORDER BY p.code',
            [$userId]
        );
    }

    public static function saveOverride(int $userId, int $permissionId, string $effect, ?string $expiresAt, string $reason): void
    {
        $db = Database::instance();
        $db->query(
            'INSERT INTO user_permissions (user_id, permission_id, effect, expires_at, reason, granted_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE effect = VALUES(effect), expires_at = VALUES(expires_at), reason = VALUES(reason), granted_by = VALUES(granted_by)',
            [
                $userId,
                $permissionId,
                $effect,
                $expiresAt !== '' ? $expiresAt : null,
                $reason !== '' ? $reason : null,
                Auth::id(),
                Database::now(),
            ]
        );
        Rbac::flush();
        Audit::log('user.permission_override_saved', 'core', 'users', $userId, null, [
            'permission_id' => $permissionId,
            'effect' => $effect,
            'expires_at' => $expiresAt,
        ]);
    }

    public static function removeOverride(int $userId, int $permissionId): void
    {
        Database::instance()->delete('user_permissions', ['user_id' => $userId, 'permission_id' => $permissionId]);
        Rbac::flush();
        Audit::log('user.permission_override_removed', 'core', 'users', $userId, ['permission_id' => $permissionId], null);
    }
}