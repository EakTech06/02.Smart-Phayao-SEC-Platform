<?php

declare(strict_types=1);

namespace Core;

final class Session
{
    private static array $flashCache = [];

    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $cfg = (array) Config::get('session', []);
        $idle = (int) ($cfg['idle_timeout'] ?? 3600);
        $absolute = (int) ($cfg['absolute_timeout'] ?? 43200);
        $secure = $cfg['secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) $idle);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        session_name((string) ($cfg['name'] ?? 'SPSECSESSID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => (bool) $secure,
            'httponly' => true,
            'samesite' => (string) ($cfg['samesite'] ?? 'Lax'),
        ]);
        session_set_save_handler(new DbSessionHandler(Database::instance()), true);
        session_start();

        self::enforceTimeouts($idle, $absolute);
    }

    private static function enforceTimeouts(int $idle, int $absolute): void
    {
        $now = time();
        $created = (int) ($_SESSION['_created'] ?? $now);
        $last = (int) ($_SESSION['_last_activity'] ?? $now);

        if (($now - $last) > $idle || ($now - $created) > $absolute) {
            $wasLoggedIn = isset($_SESSION['_uid']);
            self::invalidate();
            if ($wasLoggedIn) {
                self::flash('warning', 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่');
            }
        }
        $_SESSION['_created'] ??= $now;
        $_SESSION['_last_activity'] = $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$flashCache)) {
            return self::$flashCache[$key];
        }
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return self::$flashCache[$key] = $value;
    }

    /** ป้องกัน Session Fixation: เรียกทุกครั้งที่ระดับสิทธิ์เปลี่ยน (login / 2FA / เปลี่ยนรหัส) */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_created'] = time();
    }

    public static function invalidate(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_created'] = time();
        $_SESSION['_last_activity'] = time();
        self::$flashCache = [];
    }
}