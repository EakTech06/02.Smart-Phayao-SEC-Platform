<?php

declare(strict_types=1);

namespace Core;

final class Security
{
    /** Escape output ป้องกัน XSS ใช้ผ่าน e() ทุกครั้งที่แสดงข้อมูล */
    public static function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /** ลบ control characters และ trim */
    public static function clean(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
    }

    public static function maskNationalId(string $value): string
    {
        $digits = (string) preg_replace('/\D/', '', $value);

        return strlen($digits) === 13 ? str_repeat('*', 9) . substr($digits, -4) : '';
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}