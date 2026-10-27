<?php

declare(strict_types=1);

namespace Core;

final class Config
{
    private static array $items = [];

    public static function load(string $dir): void
    {
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            if ($key === 'local' || str_contains($key, '.')) {
                continue;
            }
            self::$items[$key] = require $file;
        }

        if (is_file($dir . '/local.php')) {
            self::$items = array_replace_recursive(self::$items, require $dir . '/local.php');
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}