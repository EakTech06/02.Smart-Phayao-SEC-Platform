<?php

declare(strict_types=1);

/**
 * การส่งอีเมล
 * - apps_script: ส่งผ่าน Gmail ของบัญชี Google Workspace ที่ Deploy Apps Script (ไม่ต้องใช้ Composer/PHPMailer บนโฮสต์)
 * - log: เขียนลง storage/logs/mail.log (สำหรับเครื่อง local)
 */
return [
    'driver' => 'apps_script',
    'from_name' => 'Smart Phayao SEC Platform',
];
