<?php

declare(strict_types=1);

return [
    'user_max_attempts' => 5,
    'user_window' => 900,
    'user_lock' => 900,
    'ip_max_attempts' => 20,
    'ip_window' => 900,
    'ip_lock' => 1800,
    'password_min_length' => 8,
    'password_history' => 5,
    'require_2fa_roles' => ['super_admin'], // Role ที่บังคับตั้งค่า 2FA ก่อนเข้าใช้งาน
    'two_factor_enabled' => true, // ปิดได้เฉพาะ app.env = local (ดู Core\Auth::twoFactorActive)
];