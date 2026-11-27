<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Database;
use Modules\CoreAdmin\Services\NotificationService;

/**
 * แจ้งเตือนกำหนดส่งงานวิจัยตามรอบการส่ง
 * - ใกล้ครบกำหนด 7 / 3 / 1 วัน: แจ้งครู (ผู้มีสิทธิ์ส่งงาน) ที่ยังไม่ได้ส่งงานในปีการศึกษา/ภาคเรียนนั้น
 * - เลยกำหนด: แจ้งครูที่ยังไม่ส่ง และแจ้งผู้ตรวจเป็นจำนวนสรุป
 * แต่ละระดับแจ้งครั้งเดียวต่อรอบ (research_round_alerts) ถ้าระบบไม่ได้ตรวจในวันนั้นพอดี จะแจ้งระดับที่ใกล้ที่สุดแทน
 */
final class ResearchReminderService
{
    private const CHECKED_KEY = 'academic.research_checked_on';

    /** ตรวจวันละครั้ง (เรียกจากแดชบอร์ด) — ล้มเหลวได้โดยไม่กระทบหน้าที่เรียก */
    public static function runDaily(): ?int
    {
        $today = date('Y-m-d');
        try {
            $db = Database::instance();
            $db->query(
                "INSERT IGNORE INTO settings (setting_group, setting_key, setting_value, value_type, description)
                 VALUES ('academic', ?, NULL, 'string', 'วันที่ตรวจแจ้งเตือนกำหนดส่งงานวิจัยครั้งล่าสุด (ระบบบันทึกเอง)')",
                [self::CHECKED_KEY]
            );
            $claimed = $db->query(
                'UPDATE settings SET setting_value = ? WHERE setting_key = ? AND (setting_value IS NULL OR setting_value <> ?)',
                [$today, self::CHECKED_KEY, $today]
            )->rowCount() === 1;

            return $claimed ? self::run() : null;
        } catch (\Throwable $e) {
            error_log('research reminder failed: ' . $e->getMessage());

            return null;
        }
    }

    /** @return int จำนวนการแจ้งเตือนที่ส่ง */
    public static function run(?string $today = null): int
    {
        $today ??= date('Y-m-d');
        $db = Database::instance();
        $rounds = $db->fetchAll(
            "SELECT r.*, DATEDIFF(r.due_on, ?) AS days_left FROM research_rounds r
             WHERE r.is_active = 1 AND r.kind = 'research' AND (r.opens_on IS NULL OR r.opens_on <= ?)
               AND DATEDIFF(r.due_on, ?) <= ?",
            [$today, $today, $today, max(ResearchLookup::REMIND_DAYS)]
        );
        $sent = 0;
        foreach ($rounds as $round) {
            $key = self::alertKey((int) $round['days_left']);
            if ($key === null) {
                continue;
            }
            $pending = self::pendingUsers($round);
            // จองระดับนี้แบบอะตอมมิก (กันแจ้งซ้ำเมื่อรันพร้อมกัน)
            $claimed = $db->query(
                'INSERT IGNORE INTO research_round_alerts (round_id, alert_key, recipients) VALUES (?, ?, ?)',
                [(int) $round['id'], $key, count($pending)]
            )->rowCount() === 1;
            if (!$claimed || $pending === []) {
                continue;
            }
            $due = thai_date((string) $round['due_on'], false, true);
            $link = '/academic/research/works/create';
            if ($key === 'overdue') {
                $sent += NotificationService::notify($pending, ResearchLookup::NOTIFY_TYPE,
                    'เลยกำหนดส่งงานวิจัย: ' . $round['title'], "ครบกำหนดส่งเมื่อ {$due} และยังไม่มีงานของคุณในรอบนี้", $link, true);
                $reviewers = NotificationService::usersWithPermission(ResearchLookup::PERM_REVIEW);
                $sent += NotificationService::notify($reviewers, ResearchLookup::NOTIFY_TYPE,
                    'เลยกำหนดส่งงานวิจัย: ' . $round['title'], 'ครูที่ยังไม่ส่งงาน ' . count($pending) . " คน (ครบกำหนด {$due})",
                    '/academic/research/works?year_id=' . (int) $round['academic_year_id'] . '&semester=' . (int) $round['semester']);
            } else {
                $days = (int) $round['days_left'];
                $when = $days <= 0 ? 'วันนี้' : "อีก {$days} วัน";
                $sent += NotificationService::notify($pending, ResearchLookup::NOTIFY_TYPE,
                    "ใกล้ครบกำหนดส่งงานวิจัย ({$when}): " . $round['title'], "ครบกำหนดส่ง {$due}", $link, true);
            }
        }

        return $sent;
    }

    /** ระดับการแจ้งเตือนของจำนวนวันที่เหลือ (null = ยังไม่ถึงเวลาแจ้ง) */
    public static function alertKey(int $daysLeft): ?string
    {
        if ($daysLeft < 0) {
            return 'overdue';
        }
        $levels = ResearchLookup::REMIND_DAYS;
        sort($levels);
        foreach ($levels as $level) {
            if ($daysLeft <= $level) {
                return 'd' . $level;
            }
        }

        return null;
    }

    /** @return list<int> ผู้มีสิทธิ์ส่งงานที่ยังไม่มีงาน (ส่งแล้ว ไม่นับร่าง/ยกเลิก) ในปีการศึกษา/ภาคเรียนของรอบ */
    public static function pendingUsers(array $round): array
    {
        $submitters = NotificationService::usersWithPermission(ResearchLookup::PERM_SUBMIT);
        if ($submitters === []) {
            return [];
        }
        $done = array_map('intval', array_column(Database::instance()->fetchAll(
            "SELECT DISTINCT owner_user_id FROM research_works
             WHERE academic_year_id = ? AND semester = ? AND deleted_at IS NULL AND status NOT IN ('draft', 'cancelled')",
            [(int) $round['academic_year_id'], (int) $round['semester']]
        ), 'owner_user_id'));

        return array_values(array_diff($submitters, $done));
    }
}
