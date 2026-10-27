<?php

declare(strict_types=1);

namespace Middleware;

use Core\Auth;
use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;
use Core\Session;

final class PasswordFreshMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $user = Auth::user();
        if ($user !== null && (int) $user['must_change_password'] === 1) {
            if ($request->expectsJson()) {
                return Response::fail(['message' => 'กรุณาเปลี่ยนรหัสผ่านก่อนใช้งาน', 'reason' => 'password_change_required'], 403);
            }
            Session::flash('warning', 'กรุณาเปลี่ยนรหัสผ่านก่อนเริ่มใช้งานระบบ');

            return Response::redirect('/password/change');
        }

        return $next($request);
    }
}