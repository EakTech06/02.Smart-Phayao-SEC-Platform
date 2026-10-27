<?php

declare(strict_types=1);

namespace Core;

final class Password
{
    public static function algo(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    private static function options(): array
    {
        return self::algo() === PASSWORD_BCRYPT ? ['cost' => 12] : [];
    }

    public static function hash(string $plain): string
    {
        return password_hash($plain, self::algo(), self::options());
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algo(), self::options());
    }

    /** @return list<string> */
    public static function policyErrors(string $password, string $username = ''): array
    {
        $min = (int) Config::get('security.password_min_length', 8);
        $errors = [];
        if (mb_strlen($password) < $min) {
            $errors[] = "ต้องมีความยาวอย่างน้อย {$min} ตัวอักษร";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'ต้องมีตัวอักษรภาษาอังกฤษพิมพ์เล็กอย่างน้อย 1 ตัว';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'ต้องมีตัวอักษรภาษาอังกฤษพิมพ์ใหญ่อย่างน้อย 1 ตัว';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'ต้องมีตัวเลขอย่างน้อย 1 ตัว';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'ต้องมีอักขระพิเศษอย่างน้อย 1 ตัว';
        }
        if ($username !== '' && stripos($password, $username) !== false) {
            $errors[] = 'ห้ามมีชื่อผู้ใช้อยู่ในรหัสผ่าน';
        }
        if (preg_match('/\d{13}/', $password)) {
            $errors[] = 'ห้ามใช้เลขประจำตัวประชาชนเป็นรหัสผ่าน';
        }

        return $errors;
    }

    public static function isReused(int $userId, string $plain): bool
    {
        $db = Database::instance();
        $limit = max(1, (int) Config::get('security.password_history', 5));
        $hashes = array_column(
            $db->fetchAll('SELECT password_hash FROM password_history WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit, [$userId]),
            'password_hash'
        );
        $hashes[] = $db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$userId]);

        foreach ($hashes as $hash) {
            if (is_string($hash) && $hash !== '' && password_verify($plain, $hash)) {
                return true;
            }
        }

        return false;
    }

    public static function change(int $userId, string $plain): void
    {
        $hash = self::hash($plain);
        $keep = max(1, (int) Config::get('security.password_history', 5));

        Database::instance()->transaction(static function (Database $db) use ($userId, $hash, $keep): void {
            $db->update('users', [
                'password_hash' => $hash,
                'must_change_password' => 0,
                'password_changed_at' => Database::now(),
            ], ['id' => $userId]);
            $db->insert('password_history', ['user_id' => $userId, 'password_hash' => $hash, 'created_at' => Database::now()]);
            $db->query(
                'DELETE FROM password_history WHERE user_id = ? AND id NOT IN (
                    SELECT id FROM (SELECT id FROM password_history WHERE user_id = ? ORDER BY id DESC LIMIT ' . $keep . ') AS keep_rows
                 )',
                [$userId, $userId]
            );
            // บังคับออกจากระบบทุกอุปกรณ์อื่น
            $db->query('DELETE FROM sessions WHERE user_id = ? AND id <> ?', [$userId, (string) session_id()]);
        });
    }
}