<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Models;

use Core\Model;

final class ServiceRoomModel extends Model
{
    protected static string $table = 'service_rooms';

    private const SELECT = 'SELECT sr.id, sr.room_code, sr.academic_year_id, sr.name_th, sr.grade_level,
            sr.created_at, sr.updated_at, ay.year_be
        FROM service_rooms sr
        JOIN academic_years ay ON ay.id = sr.academic_year_id';

    /**
     * แต่ละแถวมี teachers = รายชื่อครูประจำชั้น (เรียงครูหลักก่อน)
     * @param array{academic_year_id?: int, q?: string} $filters
     */
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
            $sql .= " AND (sr.room_code LIKE ? ESCAPE '!' OR sr.name_th LIKE ? ESCAPE '!' OR sr.grade_level LIKE ? ESCAPE '!'
                      OR EXISTS (SELECT 1 FROM service_room_teachers st JOIN employees e ON e.id = st.employee_id
                                 WHERE st.service_room_id = sr.id AND (e.first_name LIKE ? ESCAPE '!' OR e.last_name LIKE ? ESCAPE '!')))";
            array_push($params, $like, $like, $like, $like, $like);
        }

        return self::withTeachers(static::db()->fetchAll($sql . ' ORDER BY ay.year_be DESC, sr.room_code', $params));
    }

    public static function findDetail(int $id): ?array
    {
        $room = static::db()->fetch(self::SELECT . ' WHERE sr.id = ? LIMIT 1', [$id]);

        return $room === null ? null : self::withTeachers([$room])[0];
    }

    /** @return list<int> employee_id ของครูประจำชั้นห้องนี้ (ครูหลักก่อน) */
    public static function teacherIds(int $roomId): array
    {
        return array_map('intval', array_column(static::db()->fetchAll(
            'SELECT employee_id FROM service_room_teachers WHERE service_room_id = ? ORDER BY sort_order, employee_id',
            [$roomId]
        ), 'employee_id'));
    }

    /**
     * ห้องที่ครูแต่ละคนเป็นครูประจำชั้นอยู่ (ไม่นับห้อง $exceptRoomId) ใช้ซ่อนครูที่มีห้องแล้วในฟอร์ม
     * @return array<int, array<int, string>> employee_id → [academic_year_id → room_code]
     */
    public static function roomAssignments(?int $exceptRoomId): array
    {
        $map = [];
        foreach (static::db()->fetchAll(
            'SELECT st.employee_id, sr.academic_year_id, sr.room_code
             FROM service_room_teachers st JOIN service_rooms sr ON sr.id = st.service_room_id
             WHERE sr.id <> ? ORDER BY sr.room_code',
            [$exceptRoomId ?? 0]
        ) as $row) {
            $map[(int) $row['employee_id']][(int) $row['academic_year_id']] ??= (string) $row['room_code'];
        }

        return $map;
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

    /**
     * บุคลากรที่เลือกเป็นครูประจำชั้นได้ (สถานะปฏิบัติงาน) + คนที่ถูกเลือกอยู่แล้ว แม้พ้นสภาพ เพื่อไม่ให้ค่าหายตอนแก้ไข
     * @param list<int> $includeIds
     */
    public static function teacherOptions(array $includeIds = []): array
    {
        $includeIds = $includeIds === [] ? [0] : array_values(array_map('intval', $includeIds));

        return static::db()->fetchAll(
            "SELECT e.id, e.employee_code, e.title_name, e.first_name, e.last_name, e.position, e.status, d.name_th AS department_name
             FROM employees e LEFT JOIN departments d ON d.id = e.department_id
             WHERE (e.deleted_at IS NULL AND e.status = 'active') OR e.id IN (" . implode(',', array_fill(0, count($includeIds), '?')) . ')
             ORDER BY e.first_name, e.last_name',
            $includeIds
        );
    }

    /** เติม teachers ให้ทุกห้องด้วย query เดียว */
    private static function withTeachers(array $rooms): array
    {
        if ($rooms === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rooms);
        $rows = static::db()->fetchAll(
            'SELECT st.service_room_id, st.sort_order, e.id, e.employee_code, e.title_name, e.first_name, e.last_name,
                    e.position, e.status, e.deleted_at
             FROM service_room_teachers st JOIN employees e ON e.id = st.employee_id
             WHERE st.service_room_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
             ORDER BY st.sort_order, e.first_name, e.last_name',
            $ids
        );
        $byRoom = [];
        foreach ($rows as $row) {
            $byRoom[(int) $row['service_room_id']][] = $row;
        }
        foreach ($rooms as &$room) {
            $room['teachers'] = $byRoom[(int) $room['id']] ?? [];
        }

        return $rooms;
    }
}
