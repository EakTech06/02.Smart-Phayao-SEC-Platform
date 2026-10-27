<?php

declare(strict_types=1);

namespace Middleware;

use Core\MiddlewareInterface;
use Core\Request;
use Core\Response;
use Core\Session;

/** อนุญาตเข้าหน้า 2FA เฉพาะผู้ที่ผ่านรหัสผ่านแล้วภายใน 5 นาที */
final class TwoFactorPendingMiddleware implements MiddlewareInterface
{
    private const TTL = 300;

    public function handle(Request $request, callable $next): Response
    {
        $userId = Session::get('_2fa_uid');
        $started = (int) Session::get('_2fa_started', 0);

        if (!is_int($userId) || (time() - $started) > self::TTL) {
            Session::forget('_2fa_uid');
            Session::forget('_2fa_started');
            Session::flash('warning', 'หมดเวลายืนยันตัวตน กรุณาเข้าสู่ระบบอีกครั้ง');

            return Response::redirect('/login');
        }

        return $next($request);
    }
}