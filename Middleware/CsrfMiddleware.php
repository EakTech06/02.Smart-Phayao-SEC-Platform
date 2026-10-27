<?php

declare(strict_types=1);

namespace Middleware;

use Core\Csrf;
use Core\Exceptions\HttpException;
use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                throw new HttpException(419);
            }
        }

        return $next($request);
    }
}