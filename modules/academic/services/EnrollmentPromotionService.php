<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Audit;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Rbac;

/**
 * Phase 5B: ยกยอดการรับบริการไปปีการศึกษาใหม่ / เลื่อนชั้นทั้งห้อง
 * คัดลอกรายชื่อของปีต้นทางไปปีปลายทางในครั้งเดียว แล้วค่อยแก้เฉพาะรายที่เปลี่ยน
 * ข้ามนักเรียนที่มีข้อมูลปีปลายทางแล้ว จึงกดซ้ำได้โดยไม่สร้างรายการซ้ำ
 */
final class EnrollmentPromotionService
{
    public const ROOM_MATCH = 'match';

    public const GRADE_MODES = ['same' => 'คงระดับชั้นเดิม', 'next' => 'เลื่อนระดับชั้นถัดไป'];

    /** ปีต้นทางเริ่มต้น = ปีก่อนปีปัจจุบัน, ปลายทาง = ปีปัจจุบัน */
    public static function defaultYears(): array
    {
        $years = Database::instance()->fetchAll('SELECT id, year_be, is_current FROM academic_years ORDER BY year_be');
        $to = null;
        $from = null;
        foreach ($years as $i => $y) {
            if ((int) $y['is_current'] === 1) {
                $to = (int) $y['id'];
                $from = isset($years[$i - 1]) ? (int) $years[$i - 1]['id'] : null;
            }
        }

        return [$from, $to];
    }

    /**
     * นักเรียนที่ยกยอดได้: สถานะยังอยู่ในความดูแล + มีการรับบริการในปีต้นทาง
     * @return list<array>
     */
    public static function candidates(int $fromYearId, int $toYearId, ?int $fromRoomId, ?int $departmentId): array
    {
        if (!Rbac::can('academic.students.update_all')) {
            throw new HttpException(403);
        }
        $where = ['en.academic_year_id = ?', 's.deleted_at IS NULL'];
        $params = [$toYearId, $fromYearId];
        $where[] = "s.status IN ('" . implode("','", StudentLookup::OPEN_STATUSES) . "')";
        if ($fromRoomId !== null) {
            $where[] = 'en.service_room_id = ?';
            $params[] = $fromRoomId;
        }
        if ($departmentId !== null) {
            $where[] = 'en.department_id = ?';
            $params[] = $departmentId;
        }

        return Database::instance()->fetchAll(
            "SELECT s.id, s.student_code, s.title_name, s.first_name, s.last_name, s.nickname, s.status,
                    en.service_type, en.grade_level, en.department_id, en.service_room_id, en.responsible_employee_id,
                    d.name_th AS department_name, r.room_code, r.name_th AS room_name,
                    TRIM(CONCAT(COALESCE(t.title_name, ''), t.first_name, ' ', t.last_name)) AS teacher_name, t.status AS teacher_status,
                    tgt.id AS target_enrollment_id
             FROM academic_student_enrollments en
             JOIN academic_students s ON s.id = en.student_id
             LEFT JOIN departments d ON d.id = en.department_id
             LEFT JOIN service_rooms r ON r.id = en.service_room_id
             LEFT JOIN employees t ON t.id = en.responsible_employee_id
             LEFT JOIN academic_student_enrollments tgt ON tgt.student_id = s.id AND tgt.academic_year_id = ?
             WHERE " . implode(' AND ', $where) . '
             ORDER BY r.room_code, s.student_code',
            $params
        );
    }

    /**
     * @param list<int> $studentIds
     * @param string $targetRoom '' = ไม่ระบุห้อง | 'match' = ห้องรหัสเดียวกันในปีใหม่ | id ห้อง
     * @return array{created:int, skipped:int, no_room:int}
     */
    public static function promote(int $fromYearId, int $toYearId, array $studentIds, string $targetRoom, string $gradeMode, bool $copyTeacher): array
    {
        if (!Rbac::can('academic.students.update_all')) {
            throw new HttpException(403);
        }
        $db = Database::instance();
        $errors = [];
        $from = $db->fetch('SELECT id, year_be FROM academic_years WHERE id = ?', [$fromYearId]);
        $to = $db->fetch('SELECT id, year_be, name FROM academic_years WHERE id = ?', [$toYearId]);
        if ($from === null) {
            $errors['from_year_id'][] = 'กรุณาเลือกปีการศึกษาต้นทาง';
        }
        if ($to === null) {
            $errors['to_year_id'][] = 'กรุณาเลือกปีการศึกษาปลายทาง';
        } elseif ($from !== null && (int) $to['year_be'] <= (int) $from['year_be']) {
            $errors['to_year_id'][] = 'ปีการศึกษาปลายทางต้องอยู่หลังปีต้นทาง';
        }
        if (!isset(self::GRADE_MODES[$gradeMode])) {
            $errors['grade_mode'][] = 'กรุณาเลือกวิธีกำหนดระดับชั้น';
        }
        $fixedRoomId = null;
        if ($targetRoom !== '' && $targetRoom !== self::ROOM_MATCH) {
            $fixedRoomId = (int) $targetRoom;
            if ($db->fetchColumn('SELECT 1 FROM service_rooms WHERE id = ? AND academic_year_id = ?', [$fixedRoomId, $toYearId]) === null) {
                $errors['target_room'][] = 'ห้องบริการปลายทางต้องเป็นห้องของปีการศึกษาปลายทาง';
            }
        }
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($studentIds === []) {
            $errors['student_ids'][] = 'กรุณาเลือกนักเรียนอย่างน้อย 1 คน';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // ห้องปลายทางแบบจับคู่รหัสห้อง: room_code ปีเก่า → id ห้องปีใหม่
        $roomMap = [];
        foreach ($db->fetchAll(
            'SELECT ro.id AS old_id, rn.id AS new_id FROM service_rooms ro
             JOIN service_rooms rn ON rn.room_code = ro.room_code AND rn.academic_year_id = ?
             WHERE ro.academic_year_id = ?',
            [$toYearId, $fromYearId]
        ) as $m) {
            $roomMap[(int) $m['old_id']] = (int) $m['new_id'];
        }
        $active = array_flip(array_map('intval', array_column(
            $db->fetchAll("SELECT id FROM employees WHERE deleted_at IS NULL AND status = 'active'"), 'id'
        )));

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $sources = $db->fetchAll(
            "SELECT en.* FROM academic_student_enrollments en JOIN academic_students s ON s.id = en.student_id
             WHERE en.academic_year_id = ? AND s.deleted_at IS NULL AND s.status IN ('" . implode("','", StudentLookup::OPEN_STATUSES) . "')
               AND en.student_id IN ({$placeholders})",
            array_merge([$fromYearId], $studentIds)
        );

        $result = ['created' => 0, 'skipped' => count($studentIds) - count($sources), 'no_room' => 0];
        $db->transaction(static function (Database $db) use ($sources, $toYearId, $targetRoom, $fixedRoomId, $roomMap, $gradeMode, $copyTeacher, $active, &$result): void {
            foreach ($sources as $src) {
                $exists = $db->fetchColumn(
                    'SELECT 1 FROM academic_student_enrollments WHERE student_id = ? AND academic_year_id = ?',
                    [(int) $src['student_id'], $toYearId]
                );
                if ($exists !== null) {
                    $result['skipped']++;
                    continue;
                }
                $roomId = match (true) {
                    $fixedRoomId !== null => $fixedRoomId,
                    $targetRoom === self::ROOM_MATCH && $src['service_room_id'] !== null => $roomMap[(int) $src['service_room_id']] ?? null,
                    default => null,
                };
                if ($roomId === null && $targetRoom === self::ROOM_MATCH && $src['service_room_id'] !== null) {
                    $result['no_room']++; // ปีเดิมมีห้อง แต่ปีใหม่ไม่มีห้องรหัสเดียวกัน
                }
                $teacher = $copyTeacher && $src['responsible_employee_id'] !== null && isset($active[(int) $src['responsible_employee_id']])
                    ? (int) $src['responsible_employee_id'] : null;
                $db->insert('academic_student_enrollments', [
                    'student_id' => (int) $src['student_id'],
                    'academic_year_id' => $toYearId,
                    'service_type' => $src['service_type'],
                    'department_id' => $src['department_id'],
                    'service_room_id' => $roomId,
                    'grade_level' => $gradeMode === 'next' ? StudentLookup::nextGrade($src['grade_level']) : $src['grade_level'],
                    'responsible_employee_id' => $teacher,
                    'note' => null,
                    'created_by' => Auth::id(),
                ]);
                $result['created']++;
            }
        });
        Audit::log('academic.enrollment.promoted', 'academic', 'academic_student_enrollments', null, null, [
            'from_year_id' => $fromYearId, 'to_year_id' => $toYearId, 'target_room' => $targetRoom,
            'grade_mode' => $gradeMode, 'copy_teacher' => $copyTeacher, 'result' => $result,
        ]);

        return $result;
    }
}
