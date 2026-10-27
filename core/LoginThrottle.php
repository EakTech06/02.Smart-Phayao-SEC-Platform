<?php

declare(strict_types=1);

namespace Core;

/** นับความล้มเหลว 2 ชั้น: username (5 ครั้ง/15 นาที → ล็อก 15 นาที) และ IP (20 ครั้ง/15 นาที → ล็อก 30 นาที) */
final class LoginThrottle
{
    private const COUNTED_REASONS = "('invalid_credentials','invalid_2fa')";

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function isIpLocked(string $ip): bool
    {
        return $this->hasLock('ip_address', $ip, 'ip_lock', (int) Config::get('security.ip_lock', 1800));
    }

    public function isUserLocked(string $username): bool
    {
        return $this->hasLock('username', $username, 'user_lock', (int) Config::get('security.user_lock', 900));
    }

    public function record(string $username, string $ip, bool $success, string $reason, string $userAgent = ''): void
    {
        $this->db->insert('login_attempts', [
            'username' => mb_substr($username, 0, 191),
            'ip_address' => $ip,
            'success' => $success ? 1 : 0,
            'reason' => $reason,
            'user_agent' => mb_substr($userAgent, 0, 255),
            'attempted_at' => Database::now(),
        ]);
    }

    public function recordFailure(string $username, string $ip, string $userAgent, string $reason = 'invalid_credentials'): void
    {
        $this->record($username, $ip, false, $reason, $userAgent);

        $userSince = date('Y-m-d H:i:s', time() - (int) Config::get('security.user_window', 900));
        $userFails = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username = ? AND success = 0 AND reason IN ' . self::COUNTED_REASONS . ' AND attempted_at >= ?
               AND attempted_at > COALESCE((SELECT MAX(l2.attempted_at) FROM login_attempts l2
                                            WHERE l2.username = ? AND l2.success = 1), \'1970-01-01\')',
            [$username, $userSince, $username]
        );
        if ($userFails >= (int) Config::get('security.user_max_attempts', 5) && !$this->isUserLocked($username)) {
            $this->record($username, $ip, false, 'user_lock', $userAgent);
            Audit::log('auth.user_locked', 'core', 'login_attempts', null, null, ['username' => $username, 'ip' => $ip]);
        }

        $ipSince = date('Y-m-d H:i:s', time() - (int) Config::get('security.ip_window', 900));
        $ipFails = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND success = 0 AND reason IN ' . self::COUNTED_REASONS . ' AND attempted_at >= ?',
            [$ip, $ipSince]
        );
        if ($ipFails >= (int) Config::get('security.ip_max_attempts', 20) && !$this->isIpLocked($ip)) {
            $this->record($username, $ip, false, 'ip_lock', $userAgent);
            Audit::log('auth.ip_locked', 'core', 'login_attempts', null, null, ['ip' => $ip]);
        }
    }

    private function hasLock(string $column, string $value, string $reason, int $seconds): bool
    {
        $since = date('Y-m-d H:i:s', time() - $seconds);

        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_attempts WHERE {$column} = ? AND reason = ? AND attempted_at >= ?",
            [$value, $reason, $since]
        ) > 0;
    }
}