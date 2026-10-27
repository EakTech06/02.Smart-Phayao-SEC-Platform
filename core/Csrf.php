<?php

declare(strict_types=1);

namespace Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verify(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && is_string($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . Security::e(self::token()) . '">';
    }

    public static function rotate(): void
    {
        unset($_SESSION['_csrf']);
    }
}