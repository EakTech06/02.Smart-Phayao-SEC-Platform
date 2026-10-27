<?php

declare(strict_types=1);

namespace Core;

final class View
{
    /** $view รูปแบบ "module::path/name" เช่น "core_admin::auth/login" */
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::capture(self::resolve($view), $data);
        if ($layout === null) {
            return $content;
        }

        return self::capture(self::templatePath($layout), $data + ['content' => $content]);
    }

    public static function template(string $name, array $data = []): string
    {
        return self::capture(self::templatePath($name), $data);
    }

    private static function resolve(string $view): string
    {
        if (!preg_match('#^([a-z0-9_]+)::([a-z0-9_/]+)$#', $view, $m)) {
            throw new \InvalidArgumentException('Invalid view name: ' . $view);
        }
        $file = BASE_PATH . '/modules/' . $m[1] . '/views/' . $m[2] . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        return $file;
    }

    private static function templatePath(string $name): string
    {
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Invalid template: ' . $name);
        }
        $file = BASE_PATH . '/templates/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template not found: ' . $name);
        }

        return $file;
    }

    private static function capture(string $__file, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}