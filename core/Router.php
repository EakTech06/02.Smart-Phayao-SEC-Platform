<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpException;

final class Router
{
    private array $routes = [];
    private array $groupStack = [];

    public function get(string $path, array|\Closure $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|\Closure $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array|\Closure $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, array|\Closure $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /** @param array{prefix?: string, middleware?: list<string>} $attributes */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function add(string $method, string $path, array|\Closure $handler, array $middleware): void
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= '/' . trim((string) ($group['prefix'] ?? ''), '/');
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware'] ?? []);
        }
        $full = '/' . trim((string) preg_replace('#/+#', '/', $prefix . '/' . trim($path, '/')), '/');
        $regex = '#^' . preg_replace_callback(
            '/\{([a-z_]+)(?::([^}]+))?\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $full
        ) . '$#u';

        $this->routes[] = [
            'method' => $method,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $methodMismatch = true;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return $this->runPipeline($request, $route, $params);
        }

        throw new HttpException($methodMismatch ? 405 : 404);
    }

    private function runPipeline(Request $request, array $route, array $params): Response
    {
        $next = fn (Request $r): Response => $this->callHandler($route['handler'], $r, $params);

        foreach (array_reverse($route['middleware']) as $alias) {
            $middleware = $this->makeMiddleware($alias);
            $next = static fn (Request $r): Response => $middleware->handle($r, $next);
        }

        return $next($request);
    }

    private function makeMiddleware(string $alias): MiddlewareInterface
    {
        [$name, $args] = array_pad(explode(':', $alias, 2), 2, null);
        $aliases = (array) Config::get('middleware.aliases', []);
        $class = $aliases[$name] ?? null;

        if (!is_string($class) || !class_exists($class)) {
            throw new \RuntimeException('Unknown middleware: ' . $name);
        }
        $instance = new $class(...($args !== null && $args !== '' ? explode(',', $args) : []));
        if (!$instance instanceof MiddlewareInterface) {
            throw new \RuntimeException($class . ' must implement MiddlewareInterface');
        }

        return $instance;
    }

    private function callHandler(array|\Closure $handler, Request $request, array $params): Response
    {
        if ($handler instanceof \Closure) {
            $result = $handler($request, ...$params);
        } else {
            [$class, $method] = $handler;
            if (!class_exists($class) || !method_exists($class, $method)) {
                throw new \RuntimeException("Handler not found: {$class}::{$method}");
            }
            $result = (new $class($request))->{$method}(...$params);
        }

        return $result instanceof Response ? $result : Response::html((string) $result);
    }
}