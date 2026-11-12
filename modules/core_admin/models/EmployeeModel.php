<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Models;

use Core\Model;
use Core\Rbac;

/** ตารางแกนบุคลากร (employees) + บัญชีผู้ใช้ที่ผูกแบบ 1:1 (users.employee_id) */
final class EmployeeModel extends Model
{
    protected static string $table = 'employees';
    protected static bool $softDelete = true;

    private const SELECT = "SELECT e.id, e.employee_code, e.title_name, e.first_name, e.last_name, e.nickname,
            e.gender, e.birth_date, e.position, e.position_number, e.academic_rank, e.employment_type,
            e.education_level, e.education_major, e.department_id, d.name_th AS department_name,
            e.phone, e.email, e.address, e.avatar, e.national_id_last4,
            (e.national_id_encrypted IS NOT NULL) AS has_national_id_encrypted,
            e.start_date, e.end_date, e.status, e.note, e.created_at, e.updated_at,
            u.id AS user_id, u.username, u.status AS user_status, u.last_login_at
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN users u ON u.employee_id = e.id AND u.deleted_at IS NULL";

    /**
     * รายการตาม Data Scope ของผู้ใช้ปัจจุบัน
     * @param array{q?: string, department_id?: int, status?: string, employment_type?: string, account?: string} $filters
     */
    public static function listVisible(array $filters = []): array
    {
        [$where, $params] = Rbac::scopeCondition('core.employees', 'read', 'e.department_id', 'u.id');
        $sql = self::SELECT . " WHERE e.deleted_at IS NULL AND {$where}";

        if (($filters['q'] ?? '') !== '') {
            $like = '%' . strtr((string) $filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $sql .= " AND (e.employee_code LIKE ? ESCAPE '!' OR e.first_name LIKE ? ESCAPE '!'
                        OR e.last_name LIKE ? ESCAPE '!' OR e.nickname LIKE ? ESCAPE '!' OR e.position LIKE ? ESCAPE '!')";
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($filters['department_id'])) {
            $sql .= ' AND e.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (($filters['status'] ?? '') !== '') {
            $sql .= ' AND e.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (($filters['employment_type'] ?? '') !== '') {
            $sql .= ' AND e.employment_type = ?';
            $params[] = (string) $filters['employment_type'];
        }
        if (($filters['account'] ?? '') === 'with') {
            $sql .= ' AND u.id IS NOT NULL';
        } elseif (($filters['account'] ?? '') === 'without') {
            $sql .= ' AND u.id IS NULL';
        }

        return static::db()->fetchAll($sql . ' ORDER BY d.sort_order, e.employee_code', $params);
    }

    public static function findDetail(int $id): ?array
    {
        return static::db()->fetch(self::SELECT . ' WHERE e.id = ? AND e.deleted_at IS NULL LIMIT 1', [$id]);
    }

    /** ป้องกัน IDOR: คืน null ทั้งกรณีไม่พบและไม่มีสิทธิ์อ่าน */
    public static function findVisible(int $id): ?array
    {
        $row = self::findDetail($id);
        if ($row === null) {
            return null;
        }

        return self::canAccess($row, 'read') ? $row : null;
    }

    public static function canAccess(array $row, string $action): bool
    {
        $departmentId = $row['department_id'] !== null ? (int) $row['department_id'] : null;
        $ownerUserId = $row['user_id'] !== null ? (int) $row['user_id'] : null;

        return Rbac::canAccessRecord('core.employees', $action, $ownerUserId, $departmentId);
    }

    public static function findByCode(string $code): ?array
    {
        return static::db()->fetch('SELECT * FROM employees WHERE employee_code = ? LIMIT 1', [$code]);
    }

    /** @return array<string, int> นับตามสถานะ (ภายใต้ Data Scope) */
    public static function statusCounts(): array
    {
        [$where, $params] = Rbac::scopeCondition('core.employees', 'read', 'e.department_id', 'u.id');
        $rows = static::db()->fetchAll(
            "SELECT e.status, COUNT(*) AS total,
                    SUM(u.id IS NULL AND e.status = 'active') AS no_account
             FROM employees e LEFT JOIN users u ON u.employee_id = e.id AND u.deleted_at IS NULL
             WHERE e.deleted_at IS NULL AND {$where} GROUP BY e.status",
            $params
        );
        $counts = ['all' => 0, 'no_account' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
            $counts['all'] += (int) $row['total'];
            $counts['no_account'] += (int) $row['no_account'];
        }

        return $counts;
    }

    /** บัญชีผู้ใช้ที่ยังไม่ผูกกับบุคลากร (ใช้สำหรับ "ผูกบัญชีเดิม") */
    public static function unlinkedUsers(): array
    {
        return static::db()->fetchAll(
            'SELECT id, username, email FROM users WHERE employee_id IS NULL AND deleted_at IS NULL ORDER BY username'
        );
    }
}
