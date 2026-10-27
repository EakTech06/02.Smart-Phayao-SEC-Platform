<?php

declare(strict_types=1);

use Core\Config;
use Core\Csrf;
use Core\Rbac;
use Core\Request;
use Core\Security;
use Core\Session;

function e(mixed $value): string
{
    return Security::e($value);
}

function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($dir, '/');
    }

    return $base;
}

function url(string $path = '/'): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function current_path(): string
{
    return Request::capture()->path();
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return Csrf::field();
}

function old(string $key, string $default = ''): string
{
    $old = Session::getFlash('old', []);

    return is_array($old) && isset($old[$key]) && is_scalar($old[$key]) ? (string) $old[$key] : $default;
}

function errors(): array
{
    $errors = Session::getFlash('errors', []);

    return is_array($errors) ? $errors : [];
}

function error_for(string $field): ?string
{
    return errors()[$field][0] ?? null;
}

function can(string $permission): bool
{
    return Rbac::can($permission);
}