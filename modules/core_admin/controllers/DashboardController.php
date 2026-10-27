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
        return View::render('core_admin::dashboard/profile_widget', [
            'user' => Auth::user(),
            'name' => Auth::displayName(),
            'roles' => Rbac::roles(),
        ], null);
    }
}