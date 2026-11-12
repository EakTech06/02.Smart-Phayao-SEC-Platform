<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Auth;
use Core\Database;
use Core\Exceptions\ValidationException;
use Modules\CoreAdmin\Models\ServiceRoomModel;

/** ข้อมูลห้องบริการ: รหัสห้อง R### ไม่ซ้ำภายในปีการศึกษา, ครูประจำชั้นต้องมาจากข้อมูลบุคลากร */
final class ServiceRoomService
{
    public const CODE_PATTERN = '/^R\d{3}$/';

    public const LABELS = [
        'room_code' => 'รหัสห้องบริการ',
        'academic_year_id' => 'ปีการศึกษา',
        'name_th' => 'ชื่อห้องบริการ',
        'homeroom_employee_id' => 'ครูประจำชั้น',
    ];

    public static function rules(): array
    {
        return [
            'room_code' => 'required|max:10',
            'academic_year_id' => 'required|integer',
            'name_th' => 'required|max:255',
            'homeroom_employee_id' => 'nullable|integer',
        ];
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

    public static function create(array $data): int
    {
        $row = self::row($data, null);
        $id = Database::instance()->insert('service_rooms', $row + ['created_by' => Auth::id()]);
        Audit::log('service_room.created', 'core', 'service_rooms', $id, null, self::snapshot($id));

        return $id;
    }

    public static function update(array $current, array $data): void
    {
        $id = (int) $current['id'];
        $before = self::snapshot($id);
        Database::instance()->update('service_rooms', self::row($data, $id) + ['updated_by' => Auth::id()], ['id' => $id]);
        Audit::log('service_room.updated', 'core', 'service_rooms', $id, $before, self::snapshot($id));
    }

    public static function delete(array $current): void
    {
        $id = (int) $current['id'];
        $before = self::snapshot($id);
        Database::instance()->delete('service_rooms', ['id' => $id]);
        Audit::log('service_room.deleted', 'core', 'service_rooms', $id, $before);
    }

    public static function teacherName(array $room): string
    {
        return trim(($room['title_name'] ?? '') . ($room['first_name'] ?? '') . ' ' . ($room['last_name'] ?? ''));
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

        $teacherId = !empty($data['homeroom_employee_id']) ? (int) $data['homeroom_employee_id'] : null;
        if ($teacherId !== null) {
            $teacher = $db->fetch('SELECT id, status, deleted_at FROM employees WHERE id = ?', [$teacherId]);
            $unchanged = $id !== null
                && (int) $db->fetchColumn('SELECT homeroom_employee_id FROM service_rooms WHERE id = ?', [$id]) === $teacherId;
            if ($teacher === null) {
                $errors['homeroom_employee_id'][] = 'ไม่พบบุคลากรที่เลือก';
            } elseif (!$unchanged && ($teacher['deleted_at'] !== null || $teacher['status'] !== 'active')) {
                $errors['homeroom_employee_id'][] = 'ครูประจำชั้นต้องเป็นบุคลากรสถานะปฏิบัติงาน';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'room_code' => $code,
            'academic_year_id' => $yearId,
            'name_th' => trim((string) $data['name_th']),
            'homeroom_employee_id' => $teacherId,
        ];
    }

    private static function snapshot(int $id): array
    {
        $room = ServiceRoomModel::findDetail($id) ?? [];

        return [
            'room_code' => $room['room_code'] ?? null,
            'year_be' => $room['year_be'] ?? null,
            'name_th' => $room['name_th'] ?? null,
            'homeroom_employee_id' => $room['homeroom_employee_id'] ?? null,
            'homeroom' => $room !== [] ? self::teacherName($room) : null,
        ];
    }
}
