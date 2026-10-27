<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\LoginThrottle;
use Core\Password;
use Core\Response;
use Core\Session;
use Core\Totp;
use Core\TwoFactor;

final class AuthController extends Controller
{
    private const MAX_2FA_TRIES = 5;

    public function showLogin(): Response
    {
        return $this->view('core_admin::auth/login', ['title' => 'เข้าสู่ระบบ'], 'auth_layout');
    }

    public function login(): Response
    {
        $data = $this->validate(
            ['login' => 'required|max:191', 'password' => 'required|raw|max:255'],
            ['login' => 'ชื่อผู้ใช้หรืออีเมล', 'password' => 'รหัสผ่าน']
        );
        $result = Auth::attempt((string) $data['login'], (string) $data['password'], $this->request);

        return match ($result) {
            Auth::OK => $this->redirectAfterLogin(),
            Auth::NEED_2FA => $this->redirect('/2fa'),
            Auth::NEED_2FA_SETUP => $this->redirect('/2fa/setup'),
            Auth::LOCKED => $this->loginError('ระงับการเข้าสู่ระบบชั่วคราวเนื่องจากพยายามผิดหลายครั้ง กรุณาลองใหม่ภายหลัง', (string) $data['login']),
            Auth::INACTIVE => $this->loginError('บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ', (string) $data['login']),
            default => $this->loginError('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', (string) $data['login']),
        };
    }

    public function showTwoFactor(): Response
    {
        if (!TwoFactor::isEnabled($this->pendingUserId())) {
            return $this->redirect('/2fa/setup');
        }

        return $this->view('core_admin::auth/two_factor', ['title' => 'ยืนยันตัวตน 2 ขั้นตอน'], 'auth_layout');
    }

    public function verifyTwoFactor(): Response
    {
        $userId = $this->pendingUserId();
        $data = $this->validate(['code' => 'required|max:20'], ['code' => 'รหัสยืนยัน']);

        if (TwoFactor::verify($userId, (string) $data['code'])) {
            Auth::completeLogin($userId, $this->request);

            return $this->redirectAfterLogin();
        }

        return $this->twoFactorFailed($userId, '/2fa');
    }

    public function showTwoFactorSetup(): Response
    {
        $userId = $this->pendingUserId();
        if (TwoFactor::isEnabled($userId)) {
            return $this->redirect('/2fa');
        }
        $user = Database::instance()->fetch('SELECT username, email FROM users WHERE id = ?', [$userId])
            ?? throw new HttpException(404);
        $secret = TwoFactor::pendingSecret($userId);
        $account = (string) ($user['email'] ?: $user['username']);

        return $this->view('core_admin::auth/two_factor_setup', [
            'title' => 'ตั้งค่าการยืนยันตัวตน 2 ขั้นตอน',
            'secret' => $secret,
            'uri' => Totp::uri($secret, $account, (string) config('app.name')),
        ], 'auth_layout');
    }

    public function confirmTwoFactorSetup(): Response
    {
        $userId = $this->pendingUserId();
        $data = $this->validate(['code' => 'required|digits:6'], ['code' => 'รหัส 6 หลัก']);

        $codes = TwoFactor::confirmSetup($userId, (string) $data['code']);
        if ($codes === null) {
            return $this->twoFactorFailed($userId, '/2fa/setup');
        }
        Audit::log('auth.2fa_enabled', 'core', 'users', $userId, null, null, $userId);
        Auth::completeLogin($userId, $this->request);

        return $this->view('core_admin::auth/recovery_codes', ['title' => 'รหัสกู้คืน', 'codes' => $codes], 'auth_layout');
    }

    public function logout(): Response
    {
        Auth::logout();
        Session::flash('success', 'ออกจากระบบเรียบร้อยแล้ว');

        return $this->redirect('/login');
    }

    public function showChangePassword(): Response
    {
        $user = Auth::user() ?? throw new HttpException(401);

        return $this->view('core_admin::auth/change_password', [
            'title' => 'เปลี่ยนรหัสผ่าน',
            'forced' => (int) $user['must_change_password'] === 1,
            'minLength' => (int) config('security.password_min_length', 8),
        ]);
    }

    public function changePassword(): Response
    {
        $user = Auth::user() ?? throw new HttpException(401);
        $userId = (int) $user['id'];
        $data = $this->validate([
            'current_password' => 'required|raw|max:255',
            'new_password' => 'required|raw|max:255',
            'new_password_confirmation' => 'required|raw|same:new_password',
        ], [
            'current_password' => 'รหัสผ่านปัจจุบัน',
            'new_password' => 'รหัสผ่านใหม่',
            'new_password_confirmation' => 'ยืนยันรหัสผ่านใหม่',
        ]);

        $hash = (string) Database::instance()->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        if (!Password::verify((string) $data['current_password'], $hash)) {
            throw new ValidationException(['current_password' => ['รหัสผ่านปัจจุบันไม่ถูกต้อง']]);
        }
        $policy = Password::policyErrors((string) $data['new_password'], (string) $user['username']);
        if ($policy !== []) {
            throw new ValidationException(['new_password' => $policy]);
        }
        if (Password::isReused($userId, (string) $data['new_password'])) {
            $count = (int) config('security.password_history', 5);
            throw new ValidationException(['new_password' => ["ห้ามใช้รหัสผ่านซ้ำกับ {$count} ครั้งล่าสุด"]]);
        }

        Password::change($userId, (string) $data['new_password']);
        Session::regenerate();
        Csrf::rotate();
        Auth::refresh();
        Audit::log('auth.password_changed', 'core', 'users', $userId);
        Session::flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');

        return $this->redirect('/dashboard');
    }

    private function pendingUserId(): int
    {
        $userId = Session::get('_2fa_uid');
        if (!is_int($userId)) {
            throw new HttpException(401);
        }

        return $userId;
    }

    private function twoFactorFailed(int $userId, string $backTo): Response
    {
        $tries = (int) Session::get('_2fa_tries', 0) + 1;
        Session::set('_2fa_tries', $tries);
        $username = (string) Database::instance()->fetchColumn('SELECT username FROM users WHERE id = ?', [$userId]);
        (new LoginThrottle())->recordFailure($username, $this->request->ip(), $this->request->userAgent(), 'invalid_2fa');

        if ($tries >= self::MAX_2FA_TRIES) {
            Session::invalidate();
            Session::flash('error', 'ยืนยันตัวตนผิดเกินจำนวนที่กำหนด กรุณาเข้าสู่ระบบใหม่');

            return $this->redirect('/login');
        }
        Session::flash('error', 'รหัสยืนยันไม่ถูกต้อง (เหลือ ' . (self::MAX_2FA_TRIES - $tries) . ' ครั้ง)');

        return $this->redirect($backTo);
    }

    private function loginError(string $message, string $login): Response
    {
        Session::flash('error', $message);
        Session::flash('old', ['login' => $login]);

        return $this->redirect('/login');
    }

    private function redirectAfterLogin(): Response
    {
        $intended = Session::get('_intended');
        Session::forget('_intended');
        $safe = is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//');

        return $this->redirect($safe ? $intended : '/dashboard');
    }
}