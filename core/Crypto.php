<?php

declare(strict_types=1);

namespace Core;

/** AES-256-GCM (ใช้ OpenSSL ที่เปิดใช้งานใน XAMPP และ Shared Hosting เกือบทุกที่) */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    public static function key(): string
    {
        $raw = base64_decode((string) Config::get('app.key', ''), true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new \RuntimeException('APP KEY ไม่ถูกต้อง: รัน php tools/generate_key.php แล้วใส่ค่าใน config/local.php');
        }

        return $raw;
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('Invalid encrypted payload');
        }
        $plain = openssl_decrypt(
            substr($raw, 28),
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, 12, 16)
        );
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plain;
    }
}