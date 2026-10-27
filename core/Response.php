<?php

declare(strict_types=1);

namespace Core;

final class Response
{
    private array $headers;

    public function __construct(private string $body = '', private int $status = 200, array $headers = [])
    {
        $this->headers = $headers;
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(array $payload, int $status = 200): self
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return new self($body, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /** JSend: success */
    public static function success(mixed $data = null, int $status = 200): self
    {
        return self::json(['status' => 'success', 'data' => $data], $status);
    }

    /** JSend: fail (ข้อผิดพลาดฝั่งผู้ใช้ เช่น validation / สิทธิ์) */
    public static function fail(array $data, int $status = 400): self
    {
        return self::json(['status' => 'fail', 'data' => $data], $status);
    }

    /** JSend: error (ข้อผิดพลาดฝั่งเซิร์ฟเวอร์) */
    public static function error(string $message, int $status = 500, mixed $data = null): self
    {
        $payload = ['status' => 'error', 'message' => $message, 'code' => $status];
        if ($data !== null) {
            $payload['data'] = $data;
        }

        return self::json($payload, $status);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        if (str_starts_with($to, '/') && !str_starts_with($to, '//')) {
            $to = url($to);
        }

        return new self('', $status, ['Location' => $to]);
    }

    public static function back(Request $request, string $fallback = '/'): self
    {
        $referer = $request->referer();

        return $referer !== null ? new self('', 302, ['Location' => $referer]) : self::redirect($fallback);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers + self::securityHeaders() as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }

    private static function securityHeaders(): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Content-Security-Policy' => "default-src 'self'; "
                . "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
                . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
                . "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; "
                . "img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; "
                . "form-action 'self'; base-uri 'self'",
        ];
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }
}