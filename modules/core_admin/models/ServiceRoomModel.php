<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Models;

use Core\Model;

final class ServiceRoomModel extends Model
{
    protected static string $table = 'service_rooms';

    private const SELECT = "SELECT sr.id, sr.room_code, sr.academic_year_id, sr.name_th, sr.homeroom_employee_id,
            sr.created_at, sr.updated_at, ay.year_be,
            e.employee_code AS homeroom_code, e.title_name, e.first_name, e.last_name, e.position AS homeroom_position,
            e.status AS homeroom_status, e.avatar AS homeroom_avatar, e.deleted_at AS homeroom_deleted_at
        FROM service_rooms sr
        JOIN academic_years ay ON ay.id = sr.academic_year_id
        LEFT JOIN employees e ON e.id = sr.homeroom_employee_id";

    /** @param array{academic_year_id?: int, q?: string} $filters */
    public static function list(array $filters): array
    {
        $sql = self::SELECT . ' WHERE 1 = 1';
        $params = [];
        if (!empty($filters['academic_year_id'])) {
            $sql .= ' AND sr.academic_year_id = ?';
            $params[] = (int) $filters['academic_year_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
            $sql .= " AND (sr.room_code LIKE ? ESCAPE '!' OR sr.name_th LIKE ? ESCAPE '!'
                      OR e.first_name LIKE ? ESCAPE '!' OR e.last_name LIKE ? ESCAPE '!')";
            array_push($params, $like, $like, $like, $like);
        }

        return static::db()->fetchAll($sql . ' ORDER BY ay.year_be DESC, sr.room_code', $params);
    }

    public static function findDetail(int $id): ?array
    {
        return static::db()->fetch(self::SELECT . ' WHERE sr.id = ? LIMIT 1', [$id]);
    }

    public static function academicYears(): array
    {
        return static::db()->fetchAll('SELECT id, year_be, name, is_current FROM academic_years ORDER BY year_be DESC');
    }

    public static function currentAcademicYearId(): ?int
    {
        $id = static::db()->fetchColumn('SELECT id FROM academic_years ORDER BY is_current DESC, year_be DESC LIMIT 1');

        return $id !== null && $id !== false ? (int) $id : null;
    }

    /** บุคลากรที่เลือกเป็นครูประจำชั้นได้ (สถานะปฏิบัติงาน) + คนที่ถูกเลือกอยู่แล้ว แม้พ้นสภาพ เพื่อไม่ให้ค่าหายตอนแก้ไข */
    public static function teacherOptions(?int $includeId = null): array
    {
        return static::db()->fetchAll(
            "SELECT e.id, e.employee_code, e.title_name, e.first_name, e.last_name, e.position, e.status, d.name_th AS department_name
             FROM employees e LEFT JOIN departments d ON d.id = e.department_id
             WHERE (e.deleted_at IS NULL AND e.status = 'active') OR e.id = ?
             ORDER BY e.first_name, e.last_name",
            [$includeId ?? 0]
        );
    }
}
