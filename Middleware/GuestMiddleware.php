<?php

declare(strict_types=1);

namespace Middleware;

use Core\Auth;
use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return Auth::check() ? Response::redirect('/dashboard') : $next($request);
    }
}