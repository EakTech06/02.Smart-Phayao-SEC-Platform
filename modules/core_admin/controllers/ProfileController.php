<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;
use Core\Password;
use Core\Exceptions\ValidationException;
use Modules\CoreAdmin\Models\UserModel;

final class ProfileController extends Controller
{
    public function index(): Response
    {
        $user = UserModel::findDetail(Auth::id()) ?? [];
        return $this->view('core_admin::profile/index', [
            'title' => 'โปรไฟล์ของฉัน',
            'user' => $user,
        ]);
    }

    public function updatePassword(): Response
    {
        $data = $this->validate([
            'current_password' => 'required',
            'new_password' => 'required',
            'new_password_confirmation' => 'required|same:new_password',
        ]);

        $userId = Auth::id();
        $db = Database::instance();
        $hash = $db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$userId]);

        if (!is_string($hash) || !Password::verify($data['current_password'], $hash)) {
            throw new ValidationException(['current_password' => ['รหัสผ่านปัจจุบันไม่ถูกต้อง']]);
        }

        $userRow = $db->fetch('SELECT username FROM users WHERE id = ?', [$userId]);
        $policyErrors = Password::policyErrors($data['new_password'], $userRow['username'] ?? '');
        if ($policyErrors !== []) {
            throw new ValidationException(['new_password' => $policyErrors]);
        }

        if (Password::isReused($userId, $data['new_password'])) {
            throw new ValidationException(['new_password' => ['รหัสผ่านใหม่ซ้ำกับรหัสผ่าน 5 ครั้งล่าสุด']]);
        }

        Password::change($userId, $data['new_password']);
        Session::flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        return $this->redirect('/profile');
    }
}