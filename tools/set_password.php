<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/core/bootstrap.php';

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php tools/set_password.php <username> <password>\n");
    exit(1);
}

$hash = Core\Password::hash($password);
$db = Core\Database::instance();
$exists = $db->fetchColumn('SELECT id FROM users WHERE username = ?', [$username]);

if ($exists === null) {
    fwrite(STDERR, "ไม่พบผู้ใช้: {$username}\n");
    exit(1);
}

$db->update('users', ['password_hash' => $hash, 'must_change_password' => 1], ['username' => $username]);

echo "OK: ตั้งรหัสผ่านให้ {$username} แล้ว (บังคับเปลี่ยนเมื่อเข้าใช้ครั้งแรก)\n";
echo "Algorithm: " . Core\Password::algo() . "\n";
echo "Hash (นำไปฝังใน seed.sql ได้): {$hash}\n";