<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;

final class ErrorHandler
{
    private const SENSITIVE_INPUT = [
        'password', 'password_confirmation', 'current_password',
        'new_password', 'new_password_confirmation', '_csrf', 'code',
    ];

    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(\Throwable $e): void
    {
        if (PHP_SAPI === 'cli') {
            self::log($e);
            fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }

        try {
            self::toResponse($e, Request::capture())->send();
        } catch (\Throwable $inner) {
            self::log($inner);
            http_response_code(500);
            echo 'Internal Server Error';
        }
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        self::log(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo self::$debug ? $error['message'] : 'Internal Server Error';
    }

    public static function toResponse(\Throwable $e, Request $request): Response
    {
        $status = $e instanceof HttpException ? $e->getStatus() : 500;
        if ($status >= 500) {
            self::log($e);
        }
        $json = $request->expectsJson();

        if ($e instanceof ValidationException) {
            if ($json) {
                return Response::fail($e->errors(), 422);
            }
            Session::flash('errors', $e->errors());
            Session::flash('old', $request->except(self::SENSITIVE_INPUT));

            return Response::back($request);
        }

        if ($status === 419 && !$json) {
            Session::flash('error', $e->getMessage());

            return Response::back($request);
        }

        $message = ($status >= 500 && !self::$debug) ? HttpException::defaultMessage(500) : $e->getMessage();

        if ($json) {
            if ($status >= 500) {
                $debugData = self::$debug ? ['exception' => $e::class, 'at' => $e->getFile() . ':' . $e->getLine()] : null;

                return Response::error($message, $status, $debugData);
            }

            return Response::fail(['message' => $message], $status);
        }

        try {
            $html = View::template('error', [
                'status' => $status,
                'message' => $message,
                'trace' => (self::$debug && $status >= 500) ? (string) $e : null,
            ]);
        } catch (\Throwable) {
            $html = '<h1>' . $status . '</h1><p>' . Security::e($message) . '</p>';
        }

        return Response::html($html, $status);
    }

    public static function log(\Throwable $e): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $entry, FILE_APPEND | LOCK_EX);
    }
}