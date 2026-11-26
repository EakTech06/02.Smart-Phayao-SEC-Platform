<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Audit;
use Core\Database;
use Modules\CoreAdmin\Services\NotificationService;

/**
 * แจ้งเตือนบัตรประจำตัวคนพิการของนักเรียนหมดอายุ/ใกล้หมดอายุ (ภายใน StudentLookup::CARD_EXPIRY_WARN_DAYS วัน) ผ่านกระดิ่งในระบบ
 * - ผู้รับ = ผู้ใช้ที่มีสิทธิ์ดูข้อมูลอ่อนไหวของนักเรียนคนนั้น (academic.students.read_sensitive_*) ตามขอบเขตของตน
 * - ข้อความเป็นจำนวนสรุปเท่านั้น (ไม่ระบุชื่อนักเรียน เพราะเป็นข้อมูลความพิการ) พร้อมลิงก์ไปหน้าตรวจความครบถ้วน
 * แจ้งแต่ละระดับ (ใกล้หมดอายุ / หมดอายุแล้ว) ครั้งเดียวต่อวันหมดอายุ (academic_student_sensitive.card_alert_key เก็บเป็นแฮช)
 */
final class CardReminderService
{
    private const CHECKED_KEY = 'academic.card_checked_on';
    private const SCOPES = ['all', 'service_unit', 'department', 'own'];
    public const LINK = '/academic/students/completeness';

    /** ตรวจวันละครั้ง (เรียกจากแดชบอร์ด) — ล้มเหลวได้โดยไม่กระทบหน้าที่เรียก */
    public static function runDaily(): ?int
    {
        $today = date('Y-m-d');
        try {
            $db = Database::instance();
            $db->query(
                "INSERT IGNORE INTO settings (setting_group, setting_key, setting_value, value_type, description)
                 VALUES ('academic', ?, NULL, 'string', 'วันที่ตรวจแจ้งเตือนบัตรประจำตัวคนพิการครั้งล่าสุด (ระบบบันทึกเอง)')",
                [self::CHECKED_KEY]
            );
            // จองสิทธิ์ตรวจของวันนี้แบบอะตอมมิก: เปิดแดชบอร์ดพร้อมกันหลายคนจะมีเพียงคำขอเดียวที่ได้ตรวจ
            $claimed = $db->query(
                'UPDATE settings SET setting_value = ? WHERE setting_key = ? AND (setting_value IS NULL OR setting_value <> ?)',
                [$today, self::CHECKED_KEY, $today]
            )->rowCount() === 1;

            return $claimed ? self::run() : null;
        } catch (\Throwable $e) {
            error_log('card reminder failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * จำนวนบัตรหมดอายุ/ใกล้หมดอายุของนักเรียนที่ผู้ใช้มีสิทธิ์ดูข้อมูลอ่อนไหว (แสดงบนแดชบอร์ด)
     * ข้อมูลเข้ารหัสจึงต้องถอดรหัสเพื่อนับ แต่คืนเฉพาะจำนวน (ไม่มีข้อมูลรายคน)
     * @return array{expired:int, expiring:int}|null null = ไม่มีสิทธิ์ดูข้อมูลอ่อนไหวของนักเรียน
     */
    public static function countsForViewer(?int $userId = null): ?array
    {
        $cond = StudentService::scopeCondition('read_sensitive', $userId);
        if ($cond === null) {
            return null;
        }
        $statuses = StudentLookup::OPEN_STATUSES;
        $rows = Database::instance()->fetchAll(
            'SELECT ss.data_encrypted FROM academic_student_sensitive ss JOIN academic_students s ON s.id = ss.student_id
             WHERE s.deleted_at IS NULL AND s.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ") AND {$cond[0]}",
            array_merge($statuses, $cond[1])
        );
        $counts = ['expired' => 0, 'expiring' => 0];
        foreach ($rows as $row) {
            $health = StudentService::decryptSensitive((string) $row['data_encrypted']);
            $status = is_array($health) ? StudentCompleteness::cardStatus($health) : null;
            if ($status !== null && $status['state'] !== 'valid') {
                $counts[$status['state']]++;
            }
        }

        return $counts;
    }

    /** @return int จำนวนนักเรียนที่แจ้งเตือนรอบนี้ */
    public static function run(): int
    {
        $db = Database::instance();
        $statuses = StudentLookup::OPEN_STATUSES;
        $rows = $db->fetchAll(
            'SELECT ss.student_id, ss.data_encrypted, ss.card_alert_key FROM academic_student_sensitive ss
             JOIN academic_students s ON s.id = ss.student_id
             WHERE s.deleted_at IS NULL AND s.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')',
            $statuses
        );

        $alerts = []; // student_id => state
        foreach ($rows as $row) {
            $health = StudentService::decryptSensitive((string) $row['data_encrypted']);
            $status = is_array($health) ? StudentCompleteness::cardStatus($health) : null;
            if ($status === null || $status['state'] === 'valid') {
                continue;
            }
            $key = hash('sha256', $health['card_expires_at'] . '|' . $status['state']);
            if ($row['card_alert_key'] === $key) {
                continue; // แจ้งระดับนี้ไปแล้ว
            }
            // updated_at = updated_at: ไม่ให้การบันทึกสถานะแจ้งเตือนเปลี่ยนเวลาแก้ไขข้อมูล
            $db->query('UPDATE academic_student_sensitive SET card_alert_key = ?, updated_at = updated_at WHERE student_id = ?',
                [$key, (int) $row['student_id']]);
            $alerts[(int) $row['student_id']] = $status['state'];
        }
        if ($alerts === []) {
            return 0;
        }

        $recipients = [];
        foreach (self::SCOPES as $scope) {
            foreach (NotificationService::usersWithPermission("academic.students.read_sensitive_{$scope}") as $uid) {
                $recipients[$uid] = true;
            }
        }
        foreach (array_keys($recipients) as $uid) {
            $counts = ['expired' => 0, 'expiring' => 0];
            foreach ($alerts as $studentId => $state) {
                if (StudentService::canViewSensitive($studentId, $uid)) {
                    $counts[$state]++;
                }
            }
            $total = $counts['expired'] + $counts['expiring'];
            if ($total === 0) {
                continue;
            }
            $parts = array_filter([
                $counts['expired'] > 0 ? "หมดอายุแล้ว {$counts['expired']} คน" : null,
                $counts['expiring'] > 0 ? 'ใกล้หมดอายุ (ภายใน ' . StudentLookup::CARD_EXPIRY_WARN_DAYS . " วัน) {$counts['expiring']} คน" : null,
            ]);
            NotificationService::notify([$uid], 'student', 'บัตรประจำตัวคนพิการของนักเรียนที่ต้องติดตาม ' . $total . ' คน',
                implode(' · ', $parts), self::LINK);
        }
        $states = array_count_values($alerts);
        Audit::log('academic.students.card_reminder', 'academic', 'academic_students', null, null,
            ['expired' => $states['expired'] ?? 0, 'expiring' => $states['expiring'] ?? 0]);

        return count($alerts);
    }
}
