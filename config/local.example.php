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
];
