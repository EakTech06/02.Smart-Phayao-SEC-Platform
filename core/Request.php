<?php

declare(strict_types=1);

namespace Core;

final class Request
{
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $server
    ) {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $body = $_POST;

        if (str_contains(strtolower((string) ($server['CONTENT_TYPE'] ?? '')), 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw === false ? '' : $raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        if ($method === 'POST' && isset($body['_method']) && is_string($body['_method'])) {
            $override = strtoupper($body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        return new self($method, self::resolvePath($server), $_GET, $body, $server);
    }

    private static function resolvePath(array $server): string
    {
        $uri = parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = is_string($uri) ? rawurldecode($uri) : '/';
        $base = rtrim(str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/'))), '/');

        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }

        return '/' . trim($uri, '/');
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function ip(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/');
    }

    public function expectsJson(): bool
    {
        return $this->isApi()
            || str_contains((string) $this->header('Accept'), 'application/json')
            || $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function isSecure(): bool
    {
        return !empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }

    public function referer(): ?string
    {
        $ref = $this->server['HTTP_REFERER'] ?? null;
        if (!is_string($ref) || $ref === '') {
            return null;
        }
        $host = parse_url($ref, PHP_URL_HOST);
        $self = strtok((string) ($this->server['HTTP_HOST'] ?? ''), ':');

        return is_string($host) && $host === $self ? $ref : null;
    }
}