<?php

declare(strict_types=1);

namespace Core;

/** เรียก Controller ข้าม Module (HMVC Widget/Sub-request) */
final class Hmvc
{
    public static function call(string $module, string $controller, string $method, array $args = []): string
    {
        if (!in_array($module, (array) Config::get('app.modules', []), true)) {
            throw new \RuntimeException('Module not enabled: ' . $module);
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $controller) || !preg_match('/^[A-Za-z0-9_]+$/', $method)) {
            throw new \InvalidArgumentException('Invalid HMVC target');
        }
        $namespace = 'Modules\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $module))) . '\\Controllers\\';
        $class = $namespace . $controller;
        if (!class_exists($class) || !method_exists($class, $method)) {
            throw new \RuntimeException("HMVC target not found: {$class}::{$method}");
        }
        $result = (new $class(Request::capture()))->{$method}(...$args);

        return $result instanceof Response ? $result->body() : (string) $result;
    }
}