<?php

declare(strict_types=1);

// แจ้งเตือนกำหนดส่งงานวิจัย (ล่วงหน้า 7/3/1 วัน และเมื่อเลยกำหนด) ใช้กับ cron ได้ เช่น ทุกวัน 07:00
// ปกติระบบตรวจเองวันละครั้งเมื่อมีผู้เปิดแดชบอร์ดงานวิจัย สคริปต์นี้ใช้เมื่อต้องการให้แจ้งตรงเวลา
// php tools/research_reminder.php          ตรวจถ้าวันนี้ยังไม่ได้ตรวจ
// php tools/research_reminder.php --force  ตรวจทันที (แต่ละระดับแจ้งครั้งเดียวต่อรอบอยู่แล้ว)

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/core/bootstrap.php';

use Modules\Academic\Services\ResearchReminderService;

$count = in_array('--force', $argv, true) ? ResearchReminderService::run() : ResearchReminderService::runDaily();
echo $count === null ? "วันนี้ตรวจแล้ว\n" : "แจ้งเตือน {$count} รายการ\n";
