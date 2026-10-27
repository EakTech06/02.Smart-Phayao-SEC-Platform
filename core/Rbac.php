<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpException;

final class Rbac
{
    private const SCOPES = ['all', 'service_unit', 'department', 'own'];

    private static array $cache = [];

    /** @return array<string, true> */
    public static function permissions(?int $userId = null): array
    {
        $userId ??= Auth::id();
        if ($userId === null) {
            return [];
        }
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }
        $db = Database::instance();
        $granted = $db->fetchAll(
            'SELECT DISTINCT p.code FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id AND r.is_active = 1
             JOIN role_permissions rp ON rp.role_id = r.id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?',
            [$userId]
        );
        $overrides = $db->fetchAll(
            'SELECT p.code, up.effect FROM user_permissions up
             JOIN permissions p ON p.id = up.permission_id
             WHERE up.user_id = ? AND (up.expires_at IS NULL OR up.expires_at > ?)',
            [$userId, Database::now()]
        );

        $set = array_fill_keys(array_column($granted, 'code'), true);
        $deny = [];
        foreach ($overrides as $override) {
            if ($override['effect'] === 'deny') {
                $deny[$override['code']] = true;
            } else {
                $set[$override['code']] = true;
            }
        }

        return self::$cache[$userId] = array_diff_key($set, $deny);
    }

    public static function can(string $permission, ?int $userId = null): bool
    {
        return isset(self::permissions($userId)[$permission]);
    }

    public static function canAny(array $permissions, ?int $userId = null): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission, $userId)) {
                return true;
            }
        }

        return false;
    }

    /** scope ที่กว้างที่สุดที่ถือ เช่น scope('core.users', 'read') → 'department' */
    public static function scope(string $resource, string $action, ?int $userId = null): ?string
    {
        foreach (self::SCOPES as $scope) {
            if (self::can("{$resource}.{$action}_{$scope}", $userId)) {
                return $scope;
            }
        }

        return null;
    }

    /** ใช้กับ query แบบรายการ: คืน [sqlWhere, params] (OR ของทุก scope ที่ถือ) */
    public static function scopeCondition(string $resource, string $action, string $departmentColumn, string $ownerColumn): array
    {
        foreach ([$departmentColumn, $ownerColumn] as $column) {
            if (!preg_match('/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/', $column)) {
                throw new \InvalidArgumentException('Invalid column: ' . $column);
            }
        }
        $parts = [];
        $params = [];
        foreach (self::SCOPES as $scope) {
            if (!self::can("{$resource}.{$action}_{$scope}")) {
                continue;
            }
            if ($scope === 'all') {
                return ['1 = 1', []];
            }
            if ($scope === 'own') {
                $parts[] = "{$ownerColumn} = ?";
                $params[] = Auth::id();
                continue;
            }
            $ids = $scope === 'department' ? self::departmentIds() : self::serviceUnitDepartmentIds();
            if ($ids !== []) {
                $parts[] = $departmentColumn . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                array_push($params, ...$ids);
            }
        }
        if ($parts === []) {
            throw new HttpException(403);
        }

        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    /** ใช้ป้องกัน IDOR ระดับ record */
    public static function canAccessRecord(string $resource, string $action, ?int $ownerUserId, ?int $departmentId): bool
    {
        foreach (self::SCOPES as $scope) {
            if (!self::can("{$resource}.{$action}_{$scope}")) {
                continue;
            }
            $allowed = match ($scope) {
                'all' => true,
                'service_unit' => $departmentId !== null && in_array($departmentId, self::serviceUnitDepartmentIds(), true),
                'department' => $departmentId !== null && in_array($departmentId, self::departmentIds(), true),
                'own' => $ownerUserId !== null && $ownerUserId === Auth::id(),
            };
            if ($allowed) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> */
    public static function departmentIds(?int $userId = null): array
    {
        $userId ??= Auth::id();
        if ($userId === null) {
            return [];
        }
        $rows = Database::instance()->fetchAll('SELECT department_id FROM user_departments WHERE user_id = ?', [$userId]);

        return array_map('intval', array_column($rows, 'department_id'));
    }

    /** หน่วยบริการที่ผู้ใช้สังกัด + หน่วยย่อยภายใต้หน่วยบริการนั้น */
    public static function serviceUnitDepartmentIds(?int $userId = null): array
    {
        $userId ??= Auth::id();
        if ($userId === null) {
            return [];
        }
        $rows = Database::instance()->fetchAll(
            "SELECT d.id FROM departments d
             JOIN user_departments ud ON ud.department_id = d.id
             WHERE ud.user_id = ? AND d.type = 'service_unit'
             UNION
             SELECT c.id FROM departments c
             JOIN departments p ON p.id = c.parent_id AND p.type = 'service_unit'
             JOIN user_departments ud ON ud.department_id = p.id
             WHERE ud.user_id = ?",
            [$userId, $userId]
        );

        return array_map('intval', array_column($rows, 'id'));
    }

    /** @return list<array{code: string, name_th: string}> */
    public static function roles(?int $userId = null): array
    {
        $userId ??= Auth::id();
        if ($userId === null) {
            return [];
        }

        return Database::instance()->fetchAll(
            'SELECT r.code, r.name_th FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = ? AND r.is_active = 1 ORDER BY r.level',
            [$userId]
        );
    }

    public static function hasRole(string $code, ?int $userId = null): bool
    {
        return in_array($code, array_column(self::roles($userId), 'code'), true);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}