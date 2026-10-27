<?php

declare(strict_types=1);

namespace Core;

final class Setting
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            self::$cache = [];
            $rows = Database::instance()->fetchAll('SELECT setting_key, setting_value, value_type FROM settings');
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = self::cast($row['setting_value'], (string) $row['value_type']);
            }
        }

        return self::$cache[$key] ?? $default;
    }

    private static function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true),
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }
}