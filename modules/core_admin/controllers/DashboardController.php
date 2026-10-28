<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Auth;
use Core\Controller;
use Core\Database;
use Core\Rbac;
use Core\Response;
use Core\View;
use Modules\CoreAdmin\Models\UserModel;

final class DashboardController extends Controller
{
    public function home(): Response
    {
        return $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }

    public function index(): Response
    {
        $db = Database::instance();
        $stats = [
            'users' => Rbac::scope('core.users', 'read') !== null ? UserModel::countVisible() : null,
            'departments' => (int) $db->fetchColumn('SELECT COUNT(*) FROM departments WHERE is_active = 1'),
            'academic_year' => $db->fetchColumn('SELECT name FROM academic_years WHERE is_current = 1 LIMIT 1'),
            'fiscal_year' => $db->fetchColumn('SELECT name FROM fiscal_years WHERE is_current = 1 LIMIT 1'),
            'evaluation' => $db->fetchColumn('SELECT name FROM evaluation_periods WHERE is_current = 1 LIMIT 1'),
        ];
        $recent = Rbac::can('core.audit_logs.read_all')
            ? $db->fetchAll(
                'SELECT a.action, a.ip_address, a.created_at, u.username
                 FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                 ORDER BY a.id DESC LIMIT 10'
            )
            : [];

        return $this->view('core_admin::dashboard/index', [
            'title' => 'แดชบอร์ด',
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }

    /** HMVC widget: เรียกจาก View อื่นผ่าน Hmvc::call() */
    public function profileWidget(): string
    {
        $userId = \Core\Session::get('_uid') ?? (\Core\Auth::id() ?? null);
        $user = null;

        if ($userId) {
            // ดึงข้อมูลผู้ใช้พร้อม JOIN ตาราง employees เพื่อให้ได้ avatar, ชื่อ, ตำแหน่ง, วิทยฐานะ ครบถ้วน
            $user = \Core\Database::instance()->fetch('
                SELECT u.*, e.*, u.id AS id, e.id AS employee_id 
                FROM users u 
                LEFT JOIN employees e ON e.id = u.employee_id
                WHERE u.id = ?
            ', [(int) $userId]);
        }

        // หากไม่พบข้อมูลใน employees ให้ fallback ใช้ข้อมูลจาก Auth เดิม
        if (!$user) {
            $user = \Core\Auth::user() ?? [];
        }

        $fullName = trim(($user['title_name'] ?? '') . ' ' . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if ($fullName === '') {
            $fullName = \Core\Auth::displayName() ?? 'ผู้ใช้งานระบบ';
        }

        return View::render('core_admin::dashboard/profile_widget', [
            'avatar' => $user['avatar'] ?? null,
            'user'   => $user,
            'name'   => $fullName,
            'roles'  => \Core\Rbac::roles(),
        ], null);
    }
}