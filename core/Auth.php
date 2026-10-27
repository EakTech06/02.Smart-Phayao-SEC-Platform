<?php

declare(strict_types=1);

namespace Core;

final class Auth
{
    public const OK = 'ok';
    public const NEED_2FA = '2fa';
    public const NEED_2FA_SETUP = '2fa_setup';
    public const INVALID = 'invalid';
    public const LOCKED = 'locked';
    public const INACTIVE = 'inactive';

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function id(): ?int
    {
        $id = Session::get('_uid');

        return is_int($id) ? $id : null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = self::id();
        if ($id === null) {
            return null;
        }
        $user = Database::instance()->fetch(
            'SELECT u.id, u.username, u.email, u.status, u.must_change_password, u.last_login_at, u.employee_id,
                    e.employee_code, e.title_name, e.first_name, e.last_name, e.position, e.department_id
             FROM users u
             LEFT JOIN employees e ON e.id = u.employee_id
             WHERE u.id = ? AND u.deleted_at IS NULL
             LIMIT 1',
            [$id]
        );
        if ($user === null || $user['status'] !== 'active') {
            Session::invalidate();

            return self::$user = null;
        }

        return self::$user = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function displayName(): string
    {
        $user = self::user();
        if ($user === null) {
            return '';
        }
        $name = trim(($user['title_name'] ?? '') . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        return $name !== '' ? $name : (string) $user['username'];
    }

    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
        Rbac::flush();
    }

    public static function attempt(string $login, string $password, Request $request): string
    {
        $login = mb_strtolower(trim($login));
        $ip = $request->ip();
        $ua = $request->userAgent();
        $throttle = new LoginThrottle();

        if ($throttle->isIpLocked($ip)) {
            $throttle->record($login, $ip, false, 'blocked_ip', $ua);

            return self::LOCKED;
        }

        $user = Database::instance()->fetch(
            'SELECT id, username, password_hash, status FROM users
             WHERE (username = ? OR email = ?) AND deleted_at IS NULL LIMIT 1',
            [$login, $login]
        );
        $key = $user !== null ? (string) $user['username'] : $login;

        if ($throttle->isUserLocked($key)) {
            $throttle->record($key, $ip, false, 'blocked_user', $ua);

            return self::LOCKED;
        }

        if ($user === null) {
            Password::hash(bin2hex(random_bytes(8))); // ลดความต่างของเวลา (ป้องกัน user enumeration)
            $valid = false;
        } else {
            $valid = Password::verify($password, (string) $user['password_hash']);
        }

        if (!$valid) {
            $throttle->recordFailure($key, $ip, $ua);

            return self::INVALID;
        }

        $userId = (int) $user['id'];
        if ($user['status'] !== 'active') {
            $throttle->record($key, $ip, false, 'inactive', $ua);
            Audit::log('auth.login_inactive', 'core', 'users', $userId, null, null, $userId);

            return self::INACTIVE;
        }

        if (Password::needsRehash((string) $user['password_hash'])) {
            Database::instance()->update('users', ['password_hash' => Password::hash($password)], ['id' => $userId]);
        }
        /* --- ปิดการใช้งาน 2FA ชั่วคราว (ข้ามไปเข้าสู่ระบบปกติทันที) ---
        if (self::requiresTwoFactor($userId)) {
            Session::regenerate();
            Session::set('_2fa_uid', $userId);
            Session::set('_2fa_started', time());
            Session::set('_2fa_tries', 0);

            return TwoFactor::isEnabled($userId) ? self::NEED_2FA : self::NEED_2FA_SETUP;
        }
        ------------------------------------------------------------- */
        if (Config::get('security.two_factor_enabled', true) && self::requiresTwoFactor($userId)) {
            Session::regenerate();
            Session::set('_2fa_uid', $userId);
            Session::set('_2fa_started', time());
            Session::set('_2fa_tries', 0);

            return TwoFactor::isEnabled($userId) ? self::NEED_2FA : self::NEED_2FA_SETUP;
}
        self::completeLogin($userId, $request);
        return self::OK;
    }

    public static function completeLogin(int $userId, Request $request): void
    {
        Session::regenerate();
        Session::forget('_2fa_uid');
        Session::forget('_2fa_started');
        Session::forget('_2fa_tries');
        Session::set('_uid', $userId);
        Csrf::rotate();

        $db = Database::instance();
        $db->update('users', ['last_login_at' => Database::now(), 'last_login_ip' => $request->ip()], ['id' => $userId]);
        $username = (string) $db->fetchColumn('SELECT username FROM users WHERE id = ?', [$userId]);
        (new LoginThrottle())->record($username, $request->ip(), true, 'login_success', $request->userAgent());

        self::refresh();
        Audit::log('auth.login', 'core', 'users', $userId, null, null, $userId);
    }

    public static function logout(): void
    {
        $id = self::id();
        if ($id !== null) {
            Audit::log('auth.logout', 'core', 'users', $id, null, null, $id);
        }
        Session::invalidate();
        self::refresh();
    }

    private static function requiresTwoFactor(int $userId): bool
    {
        if (TwoFactor::isEnabled($userId)) {
            return true;
        }
        $roles = array_values(array_filter((array) Config::get('security.require_2fa_roles', []), 'is_string'));
        if ($roles === []) {
            return false;
        }
        $in = implode(',', array_fill(0, count($roles), '?'));

        return (int) Database::instance()->fetchColumn(
            "SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = ? AND r.code IN ({$in})",
            [$userId, ...$roles]
        ) > 0;
    }
}