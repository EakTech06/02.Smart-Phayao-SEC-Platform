<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Database;

/**
 * แจ้งเตือนใบประกอบวิชาชีพครูหมดอายุ/ใกล้หมดอายุ (ภายใน PersonnelService::LICENSE_WARN_DAYS วัน) ผ่านกระดิ่งในระบบ
 * - เจ้าของข้อมูล (บัญชีที่ผูกกับบุคลากร) ได้รับรายการของตนเอง
 * - งานบุคคล (core.employees.update_all) ได้รับสรุปจำนวน พร้อมลิงก์ไปรายการที่ต้องติดตาม
 * แจ้งแต่ละระดับ (ใกล้หมดอายุ / หมดอายุแล้ว) ครั้งเดียวต่อวันหมดอายุ (employees.license_alert_key)
 * เมื่อแก้วันหมดอายุ (ต่ออายุ) ระบบนับใหม่เอง
 */
final class LicenseReminderService
{
    private const CHECKED_KEY = 'personnel.license_checked_on';

    /** ตรวจวันละครั้ง (เรียกจากแดชบอร์ด) — ล้มเหลวได้โดยไม่กระทบหน้าที่เรียก */
    public static function runDaily(): ?int
    {
        $today = date('Y-m-d');
        try {
            $db = Database::instance();
            $db->query(
                "INSERT IGNORE INTO settings (setting_group, setting_key, setting_value, value_type, description)
                 VALUES ('personnel', ?, NULL, 'string', 'วันที่ตรวจแจ้งเตือนใบประกอบวิชาชีพครูครั้งล่าสุด (ระบบบันทึกเอง)')",
                [self::CHECKED_KEY]
            );
            // จองสิทธิ์ตรวจของวันนี้แบบอะตอมมิก: ผู้ใช้หลายคนเปิดแดชบอร์ดพร้อมกันจะมีเพียงคำขอเดียวที่ได้ตรวจ
            $claimed = $db->query(
                'UPDATE settings SET setting_value = ? WHERE setting_key = ? AND (setting_value IS NULL OR setting_value <> ?)',
                [$today, self::CHECKED_KEY, $today]
            )->rowCount() === 1;

            return $claimed ? self::run() : null;
        } catch (\Throwable $e) {
            error_log('license reminder failed: ' . $e->getMessage());

            return null;
        }
    }

    /** @return int จำนวนบุคลากรที่แจ้งเตือนรอบนี้ */
    public static function run(): int
    {
        $db = Database::instance();
        $limit = date('Y-m-d', strtotime('+' . PersonnelService::LICENSE_WARN_DAYS . ' days'));
        $rows = $db->fetchAll(
            "SELECT e.id, e.title_name, e.first_name, e.last_name, e.license_expiry_date, e.license_alert_key, u.id AS user_id
             FROM employees e LEFT JOIN users u ON u.employee_id = e.id AND u.deleted_at IS NULL
             WHERE e.deleted_at IS NULL AND e.status = 'active' AND e.license_expiry_date IS NOT NULL AND e.license_expiry_date <= ?",
            [$limit]
        );

        $counts = ['expired' => 0, 'expiring' => 0];
        foreach ($rows as $row) {
            $status = PersonnelService::licenseStatus((string) $row['license_expiry_date']);
            if ($status === null || $status['state'] === 'valid') {
                continue;
            }
            $key = $row['license_expiry_date'] . '|' . $status['state'];
            if ($row['license_alert_key'] === $key) {
                continue; // แจ้งระดับนี้ไปแล้ว
            }
            if ($row['user_id'] !== null) {
                NotificationService::notify([(int) $row['user_id']], 'personnel',
                    $status['state'] === 'expired' ? 'ใบประกอบวิชาชีพครูของคุณหมดอายุแล้ว' : 'ใบประกอบวิชาชีพครูของคุณใกล้หมดอายุ',
                    $status['text'] . ' (วันหมดอายุ ' . thai_date((string) $row['license_expiry_date'], false) . ')',
                    '/personnel/' . (int) $row['id']);
            }
            $db->update('employees', ['license_alert_key' => $key], ['id' => (int) $row['id']]);
            $counts[$status['state']]++;
        }

        $total = $counts['expired'] + $counts['expiring'];
        if ($total > 0) {
            $parts = array_filter([
                $counts['expired'] > 0 ? "หมดอายุแล้ว {$counts['expired']} คน" : null,
                $counts['expiring'] > 0 ? "ใกล้หมดอายุ (ภายใน " . PersonnelService::LICENSE_WARN_DAYS . " วัน) {$counts['expiring']} คน" : null,
            ]);
            NotificationService::notify(NotificationService::usersWithPermission('core.employees.update_all'), 'personnel',
                'ใบประกอบวิชาชีพครูที่ต้องติดตาม ' . $total . ' คน', implode(' · ', $parts), '/personnel?license=alert');
            Audit::log('personnel.license_reminder', 'core', 'employees', null, null, $counts);
        }

        return $total;
    }
}
