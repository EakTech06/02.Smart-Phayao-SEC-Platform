<?php

declare(strict_types=1);

return [
    'name' => 'Smart Phayao SEC Platform',
    // URL เต็มของระบบ ใช้สร้างลิงก์ในอีเมล เช่น https://smart.py-sec.ac.th (ตั้งใน config/local.php)
    'url' => '',
    'org' => 'ศูนย์การศึกษาพิเศษ ประจำจังหวัดพะเยา',
    'env' => 'production',
    'debug' => false,
    'timezone' => 'Asia/Bangkok',
    'key' => '',
    'modules' => ['core_admin', 'academic'],
];