<?php

declare(strict_types=1);

// คัดลอกเป็น config/local.php แล้วแก้ค่า (ไฟล์ local.php ไม่ถูกเก็บใน Git)
// สร้าง key ใหม่ด้วย: php tools/generate_key.php
return [
    'app' => [
        'env' => 'local',
        'debug' => true,
        'key' => '',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'name' => 'phayaose_smart',
        'user' => 'root',
        'pass' => '',
    ],
    'session' => [
        'secure' => false,
    ],
    'security' => [
        // ปิด 2FA ได้เฉพาะ env = local
        'two_factor_enabled' => false,
    ],
    // Phase 3: Google Workspace ผ่าน Apps Script (ดู tools/google-apps-script/README.md)
    'google' => [
        'apps_script_url' => '', // https://script.google.com/macros/s/XXXX/exec
        'shared_secret' => '',   // ค่าเดียวกับ Script Property SHARED_SECRET (อย่างน้อย 32 ตัวอักษร)
    ],
    'mail' => [
        'driver' => 'log', // production: 'apps_script' (ส่งผ่าน Gmail ของ Google Workspace)
    ],
    // 'app' => ['url' => 'https://smart.example.go.th'], // URL เต็ม ใช้สร้างลิงก์ในอีเมล
];
