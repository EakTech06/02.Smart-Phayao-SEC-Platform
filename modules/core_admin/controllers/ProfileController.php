<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\Database;
use Core\Password;
use Core\Response;
use Core\Session;

final class ProfileController extends Controller
{
    private const MAX_AVATAR_SIZE = 2 * 1024 * 1024; // 2MB
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private function avatarDir(): string
    {
        return dirname(__DIR__, 3) . '/public/uploads/avatars/';
    }

    public function index(): Response
    {
        $user = $this->currentUser();
        if (!$user) {
            return $this->redirect('/dashboard');
        }

        return $this->view('core_admin::profile/index', [
            'title' => 'โปรไฟล์ของฉัน',
            'user'  => $user,
        ]);
    }

    public function update(): Response
    {
        $user = $this->currentUser();
        if (!$user) {
            return $this->redirect('/dashboard');
        }
        $userId = (int) $user['id'];
        if ($user['employee_id'] === null) {
            return $this->backWithError('บัญชีนี้ยังไม่ได้ผูกกับข้อมูลบุคลากร กรุณาติดต่อผู้ดูแลระบบ');
        }
        $employeeId = (int) $user['employee_id'];
        $db = Database::instance();

        $title     = trim((string) $this->request->input('title_name', ''));
        $firstName = trim((string) $this->request->input('first_name', ''));
        $lastName  = trim((string) $this->request->input('last_name', ''));
        $email     = mb_strtolower(trim((string) $this->request->input('email', '')));
        $phone     = trim((string) $this->request->input('phone', ''));

        if (mb_strlen($title) > 50) {
            return $this->backWithError('คำนำหน้าต้องไม่เกิน 50 ตัวอักษร');
        }

        if ($firstName === '' || mb_strlen($firstName) > 100) {
            return $this->backWithError('กรุณากรอกชื่อ (ไม่เกิน 100 ตัวอักษร)');
        }
        if ($lastName === '' || mb_strlen($lastName) > 100) {
            return $this->backWithError('กรุณากรอกนามสกุล (ไม่เกิน 100 ตัวอักษร)');
        }
        if (mb_strlen($email) > 191 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->backWithError('รูปแบบอีเมลไม่ถูกต้อง');
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,30}$/', $phone)) {
            return $this->backWithError('รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง');
        }

        // ตรวจสอบอีเมลซ้ำในตาราง users
        $dup = $db->fetch('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $userId]);
        if ($dup) {
            return $this->backWithError('อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว');
        }

        // 1) ข้อมูลที่จะอัปเดตลงตาราง employees
        $empData = [
            'title_name' => $title !== '' ? $title : null,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email !== '' ? $email : null,
            'phone'      => $phone !== '' ? $phone : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // ---- จัดการรูปโปรไฟล์ ----
        $oldAvatar = $user['avatar'] ?? null;
        $removeOld = false;
        $dir       = $this->avatarDir();

        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['avatar'];

            if ($f['error'] !== UPLOAD_ERR_OK) {
                return $this->backWithError('อัปโหลดรูปภาพไม่สำเร็จ');
            }
            if ($f['size'] > self::MAX_AVATAR_SIZE) {
                return $this->backWithError('ขนาดไฟล์รูปภาพต้องไม่เกิน 2MB');
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if (!isset(self::ALLOWED_MIME[$mime]) || @getimagesize($f['tmp_name']) === false) {
                return $this->backWithError('รองรับเฉพาะไฟล์ภาพ JPG, PNG หรือ WebP เท่านั้น');
            }

            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                return $this->backWithError('ไม่สามารถสร้างโฟลเดอร์เก็บรูปภาพได้');
            }

            $newName = 'avatar_' . $employeeId . '_' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED_MIME[$mime];

            if (!move_uploaded_file($f['tmp_name'], $dir . $newName)) {
                return $this->backWithError('บันทึกไฟล์รูปภาพไม่สำเร็จ');
            }
            $empData['avatar'] = $newName;
            $removeOld = true;
        } elseif ($this->request->input('remove_avatar') === '1') {
            $empData['avatar'] = null;
            $removeOld = true;
        }

        // 2) อัปเดตตาราง employees (ข้อมูลส่วนตัว + รูป + อีเมล)
        $db->update('employees', $empData, ['id' => $employeeId]);

        // 3) อัปเดตตาราง users (อีเมลให้ตรงกัน + วันที่แก้ไข)
        $db->update('users', [
            'email'      => $email,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $userId]);

        // ลบรูปเก่าทิ้งถ้ามีการเปลี่ยนรูปใหม่หรือกดลบ
        if ($removeOld && $oldAvatar && is_file($dir . basename($oldAvatar))) {
            @unlink($dir . basename($oldAvatar));
        }

        Audit::log('profile.updated', 'core', 'employees', (string) $employeeId, null, [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
        ]);
        Session::flash('success', 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว');

        return $this->redirect('/profile');
    }

    public function updatePassword(): Response
    {
        $user = $this->currentUser();
        if (!$user) {
            return $this->redirect('/dashboard');
        }

        $current = (string) $this->request->input('current_password', '');
        $new     = (string) $this->request->input('new_password', '');
        $confirm = (string) $this->request->input('new_password_confirmation', '');

        $userId = (int) $user['id'];

        if (!Password::verify($current, (string) $user['password_hash'])) {
            return $this->backWithError('รหัสผ่านปัจจุบันไม่ถูกต้อง');
        }
        if ($new !== $confirm) {
            return $this->backWithError('ยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
        }
        $policy = Password::policyErrors($new, (string) $user['username']);
        if ($policy !== []) {
            return $this->backWithError('รหัสผ่านใหม่ไม่ผ่านนโยบาย: ' . implode(', ', $policy));
        }
        if (Password::isReused($userId, $new)) {
            return $this->backWithError('ห้ามใช้รหัสผ่านซ้ำกับรหัสผ่านเดิมที่เคยใช้');
        }

        // ใช้ Password::change เหมือนหน้า /password/change: Argon2 + ประวัติรหัสผ่าน + ตัด Session อุปกรณ์อื่น
        Password::change($userId, $new);
        Session::regenerate();
        Auth::refresh();

        Audit::log('profile.password_changed', 'core', 'users', (string) $userId, null, null);
        Session::flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');

        return $this->redirect('/profile');
    }

    /** ผู้ใช้ที่ล็อกอินอยู่ + ข้อมูลบุคลากรที่ผูกผ่าน users.employee_id (ระบุคอลัมน์ชัดเจน ไม่ให้ค่าทับกัน) */
    private function currentUser(): ?array
    {
        $id = Auth::id();
        if ($id === null) {
            return null;
        }

        return Database::instance()->fetch(
            "SELECT u.id, u.username, u.email, u.password_hash, u.status, u.employee_id,
                    e.title_name, e.first_name, e.last_name, e.phone, e.avatar, e.position,
                    d.name_th AS department_name
             FROM users u
             LEFT JOIN employees e ON e.id = u.employee_id AND e.deleted_at IS NULL
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = 'active'
             LIMIT 1",
            [$id]
        );
    }

    private function backWithError(string $message): Response
    {
        Session::flash('error', $message);
        return $this->redirect('/profile');
    }
}