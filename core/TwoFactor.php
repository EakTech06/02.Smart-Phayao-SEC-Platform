<?php

declare(strict_types=1);

namespace Core;

final class TwoFactor
{
    public static function isEnabled(int $userId): bool
    {
        return (int) Database::instance()->fetchColumn(
            'SELECT is_enabled FROM user_2fa WHERE user_id = ?',
            [$userId]
        ) === 1;
    }

    /** คืน secret ที่รอยืนยันอยู่ (ถ้ามี) หรือสร้างใหม่ ห้ามใช้ reset 2FA ที่เปิดอยู่แล้ว */
    public static function pendingSecret(int $userId): string
    {
        $row = Database::instance()->fetch('SELECT secret_encrypted, is_enabled FROM user_2fa WHERE user_id = ?', [$userId]);
        if ($row !== null && (int) $row['is_enabled'] === 1) {
            throw new \LogicException('2FA already enabled');
        }
        if ($row !== null) {
            return Crypto::decrypt((string) $row['secret_encrypted']);
        }
        $secret = Totp::generateSecret();
        Database::instance()->insert('user_2fa', [
            'user_id' => $userId,
            'secret_encrypted' => Crypto::encrypt($secret),
            'is_enabled' => 0,
            'created_at' => Database::now(),
        ]);

        return $secret;
    }

    /** @return list<string>|null รหัสกู้คืน (แสดงครั้งเดียว) */
    public static function confirmSetup(int $userId, string $code): ?array
    {
        $db = Database::instance();
        $row = $db->fetch('SELECT secret_encrypted, is_enabled FROM user_2fa WHERE user_id = ?', [$userId]);
        if ($row === null || (int) $row['is_enabled'] === 1) {
            return null;
        }
        $step = Totp::verify(Crypto::decrypt((string) $row['secret_encrypted']), $code);
        if ($step === null) {
            return null;
        }
        $db->update('user_2fa', [
            'is_enabled' => 1,
            'confirmed_at' => Database::now(),
            'last_used_step' => $step,
        ], ['user_id' => $userId]);

        return self::regenerateRecoveryCodes($userId);
    }

    public static function verify(int $userId, string $input): bool
    {
        $db = Database::instance();
        $row = $db->fetch('SELECT secret_encrypted, last_used_step FROM user_2fa WHERE user_id = ? AND is_enabled = 1', [$userId]);
        if ($row === null) {
            return false;
        }
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));

        if (preg_match('/^\d{6}$/', $code)) {
            $last = $row['last_used_step'] !== null ? (int) $row['last_used_step'] : null;
            $step = Totp::verify(Crypto::decrypt((string) $row['secret_encrypted']), $code, $last);
            if ($step === null) {
                return false;
            }
            $db->update('user_2fa', ['last_used_step' => $step], ['user_id' => $userId]);

            return true;
        }

        return self::useRecoveryCode($userId, $code);
    }

    /** @return list<string> */
    public static function regenerateRecoveryCodes(int $userId): array
    {
        $db = Database::instance();
        $db->delete('recovery_codes', ['user_id' => $userId]);
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $raw = Totp::base32Encode(random_bytes(5));
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
            $db->insert('recovery_codes', [
                'user_id' => $userId,
                'code_hash' => self::hashCode($raw),
                'created_at' => Database::now(),
            ]);
        }

        return $codes;
    }

    private static function useRecoveryCode(int $userId, string $normalized): bool
    {
        if (!preg_match('/^[A-Z2-7]{8}$/', $normalized)) {
            return false;
        }
        $affected = Database::instance()->query(
            'UPDATE recovery_codes SET used_at = ? WHERE user_id = ? AND code_hash = ? AND used_at IS NULL LIMIT 1',
            [Database::now(), $userId, self::hashCode($normalized)]
        )->rowCount();

        if ($affected > 0) {
            Audit::log('auth.2fa_recovery_used', 'core', 'users', $userId, null, null, $userId);
        }

        return $affected > 0;
    }

    private static function hashCode(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, Crypto::key());
    }
}