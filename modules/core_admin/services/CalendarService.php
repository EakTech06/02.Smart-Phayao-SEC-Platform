<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Auth;
use Core\Config;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\GoogleWorkspace;
use Core\GoogleWorkspaceException;
use Core\Rbac;

/**
 * ปฏิทินกลาง
 * - public: ทุกคนเห็น (สร้างได้เมื่อมี core.calendar.create_all) และซิงก์ไป Google Calendar ขององค์กร
 * - department: เห็นเฉพาะคนในฝ่าย (สร้างได้เมื่อมี create_all หรือ create_department ของฝ่ายตน)
 * - private: เห็นเฉพาะผู้สร้าง (ทุกคนสร้างได้)
 */
final class CalendarService
{
    public const VISIBILITIES = [
        'public' => 'ทั้งองค์กร',
        'department' => 'เฉพาะฝ่าย/งาน',
        'private' => 'ส่วนตัว',
    ];

    public const COLORS = [
        '#0d6efd' => 'น้ำเงิน', '#198754' => 'เขียว', '#dc3545' => 'แดง',
        '#fd7e14' => 'ส้ม', '#6f42c1' => 'ม่วง', '#20c997' => 'เขียวมิ้นต์', '#6c757d' => 'เทา',
    ];

    public const LABELS = [
        'title' => 'ชื่อกิจกรรม', 'description' => 'รายละเอียด', 'location' => 'สถานที่',
        'start_date' => 'วันที่เริ่ม', 'start_time' => 'เวลาเริ่ม', 'end_date' => 'วันที่สิ้นสุด', 'end_time' => 'เวลาสิ้นสุด',
        'visibility' => 'การมองเห็น', 'department_id' => 'ฝ่าย/งาน', 'color' => 'สี',
    ];

    private const SELECT = 'SELECT ce.*, d.name_th AS department_name,
            CONCAT(COALESCE(e.title_name, \'\'), e.first_name, \' \', e.last_name) AS creator_name
        FROM calendar_events ce
        LEFT JOIN departments d ON d.id = ce.department_id
        LEFT JOIN users u ON u.id = ce.created_by
        LEFT JOIN employees e ON e.id = u.employee_id';

    public static function rules(): array
    {
        return [
            'title' => 'required|max:255',
            'description' => 'nullable|max:5000',
            'location' => 'nullable|max:255',
            'start_date' => 'required|date',
            'start_time' => 'nullable|max:5',
            'end_date' => 'nullable|date',
            'end_time' => 'nullable|max:5',
            'visibility' => 'required|in:' . implode(',', array_keys(self::VISIBILITIES)),
            'department_id' => 'nullable|integer',
            'color' => 'nullable|in:' . implode(',', array_keys(self::COLORS)),
        ];
    }

    /** กิจกรรมที่ผู้ใช้เห็นได้และคาบเกี่ยวช่วง [from, to) */
    public static function between(string $from, string $to): array
    {
        [$where, $params] = self::visibleCondition();

        return Database::instance()->fetchAll(
            self::SELECT . " WHERE ce.deleted_at IS NULL AND {$where}
              AND ce.start_at < ? AND COALESCE(ce.end_at, ce.start_at) >= ?
             ORDER BY ce.is_all_day DESC, ce.start_at, ce.id",
            array_merge($params, [$to, $from])
        );
    }

    public static function upcoming(int $limit = 5): array
    {
        [$where, $params] = self::visibleCondition();

        return Database::instance()->fetchAll(
            self::SELECT . " WHERE ce.deleted_at IS NULL AND {$where} AND COALESCE(ce.end_at, ce.start_at) >= ?
             ORDER BY ce.start_at LIMIT " . max(1, min(20, $limit)),
            array_merge($params, [date('Y-m-d 00:00:00')])
        );
    }

    /** ป้องกัน IDOR: คืน null ทั้งกรณีไม่พบและมองไม่เห็น */
    public static function findVisible(int $id): ?array
    {
        [$where, $params] = self::visibleCondition();

        return Database::instance()->fetch(
            self::SELECT . " WHERE ce.id = ? AND ce.deleted_at IS NULL AND {$where}",
            array_merge([$id], $params)
        );
    }

    public static function canCreateAny(): bool
    {
        return Rbac::can('core.calendar.read_all');
    }

    public static function canCreate(string $visibility, ?int $departmentId): bool
    {
        return match ($visibility) {
            'private' => Rbac::can('core.calendar.read_all'),
            'public' => Rbac::can('core.calendar.create_all'),
            'department' => $departmentId !== null && (Rbac::can('core.calendar.create_all')
                || (Rbac::can('core.calendar.create_department') && in_array($departmentId, Rbac::departmentIds(), true))),
            default => false,
        };
    }

    public static function canEdit(array $event): bool
    {
        if ((int) $event['created_by'] === Auth::id()) {
            return true;
        }
        if ($event['visibility'] === 'private') {
            return false;
        }

        return self::canCreate((string) $event['visibility'], $event['department_id'] !== null ? (int) $event['department_id'] : null);
    }

    /** ตัวเลือกการมองเห็น/ฝ่ายที่ผู้ใช้สร้างได้ */
    public static function options(): array
    {
        $all = Rbac::can('core.calendar.create_all');
        $departments = Database::instance()->fetchAll('SELECT id, name_th FROM departments WHERE is_active = 1 ORDER BY sort_order, id');
        if (!$all) {
            $mine = Rbac::can('core.calendar.create_department') ? Rbac::departmentIds() : [];
            $departments = array_values(array_filter($departments, static fn (array $d): bool => in_array((int) $d['id'], $mine, true)));
        }
        $visibilities = array_filter(self::VISIBILITIES, static fn (string $key): bool => match ($key) {
            'public' => $all,
            'department' => $departments !== [],
            default => true,
        }, ARRAY_FILTER_USE_KEY);

        return ['visibilities' => $visibilities, 'departments' => $departments];
    }

    /** @return array{id: int, warning: ?string} */
    public static function create(array $data, bool $notify): array
    {
        $row = self::row($data);
        $id = Database::instance()->insert('calendar_events', $row + ['created_by' => Auth::id()]);
        $warning = self::syncGoogle($id);
        Audit::log('calendar.created', 'core', 'calendar_events', $id, null, self::snapshot($id));
        if ($notify) {
            self::notifyAudience($id, 'กิจกรรมใหม่: ');
        }

        return ['id' => $id, 'warning' => $warning];
    }

    public static function update(array $event, array $data, bool $notify): ?string
    {
        if (!self::canEdit($event)) {
            throw new HttpException(403);
        }
        $id = (int) $event['id'];
        $before = self::snapshot($id);
        Database::instance()->update('calendar_events', self::row($data) + ['updated_by' => Auth::id()], ['id' => $id]);
        $warning = self::syncGoogle($id);
        Audit::log('calendar.updated', 'core', 'calendar_events', $id, $before, self::snapshot($id));
        if ($notify) {
            self::notifyAudience($id, 'แก้ไขกิจกรรม: ');
        }

        return $warning;
    }

    public static function delete(array $event): ?string
    {
        if (!self::canEdit($event)) {
            throw new HttpException(403);
        }
        $id = (int) $event['id'];
        Database::instance()->update('calendar_events', ['deleted_at' => Database::now(), 'updated_by' => Auth::id()], ['id' => $id]);
        $warning = self::syncGoogle($id);
        Audit::log('calendar.deleted', 'core', 'calendar_events', $id, ['title' => $event['title'], 'start_at' => $event['start_at']]);

        return $warning;
    }

    /** ลิงก์เพิ่มลง Google Calendar ส่วนตัว (ไม่ต้องเชื่อม API) */
    public static function googleTemplateUrl(array $event): string
    {
        $allDay = (int) $event['is_all_day'] === 1;
        $start = strtotime((string) $event['start_at']);
        $end = strtotime((string) ($event['end_at'] ?? $event['start_at']));
        $format = static fn (int $ts): string => $allDay ? date('Ymd', $ts) : gmdate('Ymd\THis\Z', $ts);
        $endTs = $allDay ? strtotime('+1 day', $end) : max($end, $start + 3600);

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => $event['title'],
            'dates' => $format($start) . '/' . $format((int) $endTs),
            'details' => (string) ($event['description'] ?? ''),
            'location' => (string) ($event['location'] ?? ''),
        ]);
    }

    // ------------------------------------------------------------------ ภายใน

    /** @return array{0: string, 1: list<mixed>} */
    private static function visibleCondition(): array
    {
        $userId = (int) Auth::id();
        $departmentIds = Rbac::departmentIds();
        $dept = $departmentIds === [] ? '0' : implode(',', array_map('intval', $departmentIds));
        $departmentClause = Rbac::can('core.calendar.create_all')
            ? "ce.visibility = 'department'"
            : "(ce.visibility = 'department' AND ce.department_id IN ({$dept}))";

        return ["(ce.visibility = 'public' OR ce.created_by = ? OR {$departmentClause})", [$userId]];
    }

    private static function row(array $data): array
    {
        $errors = [];
        $allDay = !empty($data['is_all_day']);
        $startTime = self::time($data['start_time'] ?? null);
        $endTime = self::time($data['end_time'] ?? null);
        if (!$allDay && $startTime === null) {
            $errors['start_time'][] = 'กรุณาระบุเวลาเริ่ม (รูปแบบ HH:MM) หรือเลือก "ทั้งวัน"';
        }
        $start = $data['start_date'] . ' ' . ($allDay ? '00:00' : ($startTime ?? '00:00')) . ':00';
        $endDate = !empty($data['end_date']) ? (string) $data['end_date'] : null;
        $end = null;
        if ($allDay) {
            $end = $endDate !== null && $endDate > $data['start_date'] ? $endDate . ' 23:59:59' : null;
        } elseif ($endTime !== null || $endDate !== null) {
            $end = ($endDate ?? $data['start_date']) . ' ' . ($endTime ?? $startTime ?? '00:00') . ':00';
        }
        if ($end !== null && $end < $start) {
            $errors['end_date'][] = 'วันเวลาสิ้นสุดต้องไม่ก่อนวันเวลาเริ่ม';
        }

        $visibility = (string) $data['visibility'];
        $departmentId = !empty($data['department_id']) ? (int) $data['department_id'] : null;
        if ($visibility === 'department' && $departmentId === null) {
            $errors['department_id'][] = 'กรุณาเลือกฝ่าย/งาน';
        }
        if ($visibility !== 'department') {
            $departmentId = null;
        }
        if ($errors === [] && !self::canCreate($visibility, $departmentId)) {
            $errors['visibility'][] = 'คุณไม่มีสิทธิ์สร้างกิจกรรมระดับนี้' . ($visibility === 'department' ? 'ในฝ่ายที่เลือก' : '');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'title' => trim((string) $data['title']),
            'description' => ($data['description'] ?? '') !== '' ? (string) $data['description'] : null,
            'location' => ($data['location'] ?? '') !== '' ? (string) $data['location'] : null,
            'start_at' => $start,
            'end_at' => $end,
            'is_all_day' => $allDay ? 1 : 0,
            'visibility' => $visibility,
            'department_id' => $departmentId,
            'color' => ($data['color'] ?? '') !== '' ? (string) $data['color'] : '#0d6efd',
        ];
    }

    private static function time(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : null;
    }

    /**
     * ซิงก์กิจกรรมสาธารณะไป Google Calendar ขององค์กร (ล้มเหลวไม่ทำให้บันทึกในระบบล้ม คืนข้อความเตือนแทน)
     * กิจกรรมที่ถูกลบ/เปลี่ยนเป็นไม่สาธารณะ จะถูกลบออกจาก Google Calendar
     */
    private static function syncGoogle(int $id): ?string
    {
        if (!Config::get('google.calendar_sync', true) || !GoogleWorkspace::isConfigured()) {
            return null;
        }
        $db = Database::instance();
        $event = $db->fetch('SELECT * FROM calendar_events WHERE id = ?', [$id]);
        if ($event === null) {
            return null;
        }
        $shouldExist = $event['deleted_at'] === null && $event['visibility'] === 'public';
        try {
            if ($shouldExist) {
                $allDay = (int) $event['is_all_day'] === 1;
                $iso = static fn (?string $dt): ?string => $dt !== null ? date('c', (int) strtotime($dt)) : null;
                $end = $event['end_at'];
                if ($allDay && $end !== null) {
                    $end = date('Y-m-d 00:00:00', (int) strtotime($end . ' +1 day')); // Google: วันสิ้นสุดแบบ exclusive
                }
                $result = GoogleWorkspace::call('calendar.upsert', [
                    'eventId' => $event['google_event_id'],
                    'title' => $event['title'],
                    'description' => (string) ($event['description'] ?? ''),
                    'location' => (string) ($event['location'] ?? ''),
                    'start' => $iso((string) $event['start_at']),
                    'end' => $iso($end),
                    'allDay' => $allDay,
                ]);
                $db->update('calendar_events', [
                    'google_event_id' => (string) ($result['eventId'] ?? $event['google_event_id']),
                    'google_synced_at' => Database::now(),
                ], ['id' => $id]);
            } elseif ($event['google_event_id'] !== null) {
                GoogleWorkspace::call('calendar.delete', ['eventId' => $event['google_event_id']]);
                $db->update('calendar_events', ['google_event_id' => null, 'google_synced_at' => Database::now()], ['id' => $id]);
            }
        } catch (GoogleWorkspaceException $e) {
            error_log('[calendar sync] ' . $id . ': ' . $e->getMessage());

            return 'บันทึกในระบบแล้ว แต่ซิงก์ Google Calendar ไม่สำเร็จ: ' . $e->getMessage();
        }

        return null;
    }

    private static function notifyAudience(int $id, string $prefix): void
    {
        $event = Database::instance()->fetch('SELECT * FROM calendar_events WHERE id = ?', [$id]);
        if ($event === null || $event['visibility'] === 'private') {
            return;
        }
        $users = $event['visibility'] === 'public'
            ? NotificationService::activeUserIds()
            : NotificationService::activeUserIds([(int) $event['department_id']]);
        $users = array_values(array_diff($users, [(int) Auth::id()]));
        $when = thai_date((string) $event['start_at'], (int) $event['is_all_day'] !== 1);
        NotificationService::notify($users, 'calendar', $prefix . $event['title'],
            $when . ($event['location'] ? ' · ' . $event['location'] : ''), '/calendar/' . $id);
    }

    private static function snapshot(int $id): array
    {
        $e = Database::instance()->fetch(
            'SELECT title, start_at, end_at, is_all_day, visibility, department_id, location FROM calendar_events WHERE id = ?',
            [$id]
        );

        return $e ?? [];
    }
}
