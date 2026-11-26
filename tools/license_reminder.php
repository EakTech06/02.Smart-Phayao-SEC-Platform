<?php

declare(strict_types=1);

// แจ้งเตือนใบประกอบวิชาชีพครูหมดอายุ/ใกล้หมดอายุ (ใช้กับ cron ได้ เช่น ทุกวัน 07:00)
// ปกติระบบตรวจเองวันละครั้งเมื่อมีผู้เปิดแดชบอร์ด สคริปต์นี้ใช้เมื่อต้องการให้แจ้งตรงเวลา
// php tools/license_reminder.php          ตรวจถ้าวันนี้ยังไม่ได้ตรวจ
// php tools/license_reminder.php --force  ตรวจทันที (แจ้งเฉพาะรายการที่ยังไม่เคยแจ้งในระดับเดียวกัน)

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/core/bootstrap.php';

use Modules\CoreAdmin\Services\LicenseReminderService;

$count = in_array('--force', $argv, true) ? LicenseReminderService::run() : LicenseReminderService::runDaily();
echo $count === null ? "วันนี้ตรวจแล้ว\n" : "แจ้งเตือน {$count} รายการ\n";
