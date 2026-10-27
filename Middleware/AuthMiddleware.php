<?php

declare(strict_types=1);

namespace Middleware;

use Core\Auth;
use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;
use Core\Session;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }
        if ($request->expectsJson()) {
            return Response::fail(['message' => 'กรุณาเข้าสู่ระบบ'], 401);
        }
        if ($request->method() === 'GET') {
            Session::set('_intended', $request->path());
        }
        Session::flash('warning', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');

        return Response::redirect('/login');
    }
}