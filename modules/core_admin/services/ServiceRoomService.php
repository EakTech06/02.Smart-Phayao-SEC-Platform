<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Auth;
use Core\Database;
use Core\Exceptions\ValidationException;
use Modules\CoreAdmin\Models\ServiceRoomModel;

/**
 * ข้อมูลห้องบริการ: รหัสห้อง R### ไม่ซ้ำภายในปีการศึกษา, ระดับชั้นของห้อง,
 * ครูประจำชั้นได้หลายคน (จากข้อมูลบุคลากร คนแรก = ครูหลัก)
 */
final class ServiceRoomService
{
    public const CODE_PATTERN = '/^R\d{3}$/';

    public const MAX_TEACHERS = 5;

    /** ระดับชั้น (ใช้ร่วมกับทะเบียนนักเรียน: StudentLookup::GRADE_LEVELS) */
    public const GRADE_LEVELS = [
        'เตรียมความพร้อม', 'อนุบาล 1', 'อนุบาล 2', 'อนุบาล 3',
        'ประถมศึกษาปีที่ 1', 'ประถมศึกษาปีที่ 2', 'ประถมศึกษาปีที่ 3',
        'ประถมศึกษาปีที่ 4', 'ประถมศึกษาปีที่ 5', 'ประถมศึกษาปีที่ 6',
    ];

    public const LABELS = [
        'room_code' => 'รหัสห้องบริการ',
        'academic_year_id' => 'ปีการศึกษา',
        'name_th' => 'ชื่อห้องบริการ',
        'grade_level' => 'ระดับชั้น',
        'teacher_ids' => 'ครูประจำชั้น',
    ];

    public static function rules(): array
    {
        return [
            'room_code' => 'required|max:10',
            'academic_year_id' => 'required|integer',
            'name_th' => 'required|max:255',
            'grade_level' => 'nullable|max:50',
        ];
    }

    /** @return list<int> ครูประจำชั้นจาก input teacher_ids[] (คงลำดับ, ตัดค่าว่าง/ซ้ำ) */
    public static function teacherIdsInput(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $v) {
            if (is_scalar($v) && ctype_digit((string) $v) && (int) $v > 0 && !in_array((int) $v, $ids, true)) {
                $ids[] = (int) $v;
            }
        }

        return $ids;
    }

    /** รหัสถัดไปของปีการศึกษา (R001, R002, ...) ใช้เป็นค่าแนะนำในฟอร์ม */
    public static function nextCode(int $academicYearId): string
    {
        $max = (int) Database::instance()->fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(room_code, 2) AS UNSIGNED)) FROM service_rooms
             WHERE academic_year_id = ? AND room_code REGEXP '^R[0-9]{3}$'",
            [$academicYearId]
        );

        return sprintf('R%03d', min($max + 1, 999));
    }

    /** @return array<int, string> academic_year_id → รหัสถัดไป */
    public static function nextCodes(): array
    {
        $codes = [];
        foreach (ServiceRoomModel::academicYears() as $year) {
            $codes[(int) $year['id']] = self::nextCode((int) $year['id']);
        }

        return $codes;
    }

    /** @param list<int> $teacherIds */
    public static function create(array $data, array $teacherIds): int
    {
        $row = self::row($data, null);
        $teacherIds = self::checkTeachers($teacherIds, [], $row['academic_year_id'], null);
        $db = Database::instance();
        $id = $db->transaction(static function () use ($db, $row, $teacherIds): int {
            $id = $db->insert('service_rooms', $row + ['created_by' => Auth::id()]);
            self::saveTeachers($id, $teacherIds);

            return $id;
        });
        Audit::log('service_room.created', 'core', 'service_rooms', $id, null, self::snapshot($id));

        return $id;
    }

    /** @param list<int> $teacherIds */
    public static function update(array $current, array $data, array $teacherIds): void
    {
        $id = (int) $current['id'];
        $before = self::snapshot($id);
        $row = self::row($data, $id);
        $teacherIds = self::checkTeachers($teacherIds, ServiceRoomModel::teacherIds($id), $row['academic_year_id'], $id);
        $db = Database::instance();
        $db->transaction(static function () use ($db, $id, $row, $teacherIds): void {
            $db->update('service_rooms', $row + ['updated_by' => Auth::id()], ['id' => $id]);
            self::saveTeachers($id, $teacherIds);
        });
        Audit::log('service_room.updated', 'core', 'service_rooms', $id, $before, self::snapshot($id));
    }

    public static function delete(array $current): void
    {
        $id = (int) $current['id'];
        $before = self::snapshot($id);
        Database::instance()->delete('service_rooms', ['id' => $id]); // service_room_teachers ลบตาม (CASCADE)
        Audit::log('service_room.deleted', 'core', 'service_rooms', $id, $before);
    }

    public static function teacherName(array $teacher): string
    {
        return trim(($teacher['title_name'] ?? '') . ($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? ''));
    }

    /** ครูที่ควรเปลี่ยน (พ้นสภาพ/ไม่ปฏิบัติงาน) */
    public static function teacherInactive(array $teacher): bool
    {
        return ($teacher['deleted_at'] ?? null) !== null || ($teacher['status'] ?? '') !== 'active';
    }

    private static function row(array $data, ?int $id): array
    {
        $db = Database::instance();
        $errors = [];

        $code = strtoupper(trim((string) $data['room_code']));
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            $errors['room_code'][] = 'รหัสห้องบริการต้องเป็นรูปแบบ R ตามด้วยตัวเลข 3 หลัก เช่น R001';
        }
        $yearId = (int) $data['academic_year_id'];
        if ($db->fetchColumn('SELECT 1 FROM academic_years WHERE id = ?', [$yearId]) === null) {
            $errors['academic_year_id'][] = 'ไม่พบปีการศึกษาที่เลือก';
        } elseif ($errors === []
            && $db->fetchColumn('SELECT 1 FROM service_rooms WHERE academic_year_id = ? AND room_code = ? AND id <> ?', [$yearId, $code, $id ?? 0]) !== null) {
            $errors['room_code'][] = "รหัส {$code} มีอยู่แล้วในปีการศึกษานี้";
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $grade = trim((string) ($data['grade_level'] ?? ''));

        return [
            'room_code' => $code,
            'academic_year_id' => $yearId,
            'name_th' => trim((string) $data['name_th']),
            'grade_level' => $grade !== '' ? $grade : null,
        ];
    }

    /**
     * ครูต้องมีอยู่ในข้อมูลบุคลากรและสถานะปฏิบัติงาน (ยกเว้นคนที่เป็นครูประจำชั้นห้องนี้อยู่แล้ว)
     * และครู 1 คนเป็นครูประจำชั้นได้ 1 ห้องต่อปีการศึกษา
     * @param list<int> $teacherIds
     * @param list<int> $currentIds
     * @return list<int>
     */
    private static function checkTeachers(array $teacherIds, array $currentIds, int $yearId, ?int $roomId): array
    {
        if (count($teacherIds) > self::MAX_TEACHERS) {
            throw new ValidationException(['teacher_ids' => ['ครูประจำชั้นได้ไม่เกิน ' . self::MAX_TEACHERS . ' คน']]);
        }
        $db = Database::instance();
        foreach ($teacherIds as $teacherId) {
            $teacher = $db->fetch('SELECT id, status, deleted_at FROM employees WHERE id = ?', [$teacherId]);
            if ($teacher === null) {
                throw new ValidationException(['teacher_ids' => ['ไม่พบบุคลากรที่เลือก']]);
            }
            if (!in_array($teacherId, $currentIds, true) && self::teacherInactive($teacher)) {
                throw new ValidationException(['teacher_ids' => ['ครูประจำชั้นต้องเป็นบุคลากรสถานะปฏิบัติงาน']]);
            }
        }

        if ($teacherIds !== []) {
            $taken = $db->fetchAll(
                'SELECT e.title_name, e.first_name, e.last_name, sr.room_code, sr.name_th
                 FROM service_room_teachers st
                 JOIN service_rooms sr ON sr.id = st.service_room_id
                 JOIN employees e ON e.id = st.employee_id
                 WHERE sr.academic_year_id = ? AND sr.id <> ?
                   AND st.employee_id IN (' . implode(',', array_fill(0, count($teacherIds), '?')) . ')
                 ORDER BY sr.room_code',
                array_merge([$yearId, $roomId ?? 0], $teacherIds)
            );
            if ($taken !== []) {
                throw new ValidationException(['teacher_ids' => array_map(
                    static fn (array $t): string => self::teacherName($t) . " เป็นครูประจำชั้นห้อง {$t['room_code']} {$t['name_th']} อยู่แล้ว"
                        . ' (ครู 1 คนเป็นครูประจำชั้นได้ 1 ห้องต่อปีการศึกษา)',
                    $taken
                )]);
            }
        }

        return $teacherIds;
    }

    /** @param list<int> $teacherIds */
    private static function saveTeachers(int $roomId, array $teacherIds): void
    {
        $db = Database::instance();
        $db->delete('service_room_teachers', ['service_room_id' => $roomId]);
        foreach ($teacherIds as $i => $employeeId) {
            $db->insert('service_room_teachers', ['service_room_id' => $roomId, 'employee_id' => $employeeId, 'sort_order' => $i + 1]);
        }
    }

    private static function snapshot(int $id): array
    {
        $room = ServiceRoomModel::findDetail($id) ?? [];

        return [
            'room_code' => $room['room_code'] ?? null,
            'year_be' => $room['year_be'] ?? null,
            'name_th' => $room['name_th'] ?? null,
            'grade_level' => $room['grade_level'] ?? null,
            'teacher_ids' => array_map(static fn (array $t): int => (int) $t['id'], $room['teachers'] ?? []),
            'teachers' => array_map([self::class, 'teacherName'], $room['teachers'] ?? []),
        ];
    }
}
