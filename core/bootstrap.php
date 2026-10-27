<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('Smart Phayao SEC Platform requires PHP 8.2 or newer.');
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $parts = explode('\\', ltrim($class, '\\'));
    $name = array_pop($parts);
    $dirs = array_map(
        static fn (string $part): string => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $part)),
        $parts
    );
    $file = BASE_PATH . '/' . implode('/', $dirs) . '/' . $name . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/core/helpers.php';

Core\Config::load(BASE_PATH . '/config');
date_default_timezone_set((string) Core\Config::get('app.timezone', 'Asia/Bangkok'));
mb_internal_encoding('UTF-8');
Core\ErrorHandler::register((bool) Core\Config::get('app.debug', false));