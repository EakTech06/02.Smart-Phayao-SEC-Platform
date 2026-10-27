<?php

declare(strict_types=1);

namespace Core;

final class Audit
{
    private const SENSITIVE = [
        'password', 'password_hash', 'password_confirmation', 'current_password', 'new_password',
        'new_password_confirmation', 'secret', 'secret_encrypted', 'token', 'token_hash',
        'code', 'code_hash', '_csrf', 'national_id',
    ];

    public static function log(
        string $action,
        ?string $module = null,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null
    ): void {
        try {
            $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
            Database::instance()->insert('audit_logs', [
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'module' => $module,
                'entity_type' => $entityType,
                'entity_id' => $entityId !== null ? (string) $entityId : null,
                'old_values' => $old !== null ? json_encode(self::redact($old), JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $new !== null ? json_encode(self::redact($new), JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'request_path' => $path !== false ? mb_substr($path, 0, 255) : null,
                'created_at' => Database::now(),
            ]);
        } catch (\Throwable $e) {
            ErrorHandler::log($e); // Audit ล้มเหลวต้องไม่ทำให้งานหลักล้ม
        }
    }

    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            } elseif (is_string($value) && preg_match('/\b\d{13}\b/', $value)) {
                $data[$key] = (string) preg_replace('/\b\d{9}(\d{4})\b/', '*********$1', $value);
            }
        }

        return $data;
    }
}