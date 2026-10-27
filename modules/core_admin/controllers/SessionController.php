<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Auth;
use Core\Database;
use Core\Response;
use Core\Session;

final class SessionController extends Controller
{
    public function index(): Response
    {
        $this->authorize('core.sessions.read_all');
        $sessions = Database::instance()->fetchAll(
            'SELECT s.*, u.username, e.first_name, e.last_name FROM sessions s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN employees e ON e.id = u.employee_id
             ORDER BY s.last_activity DESC'
        );
        return $this->view('core_admin::sessions/index', [
            'title' => 'จัดการเซสชันผู้ใช้งาน',
            'sessions' => $sessions,
            'currentSessionId' => session_id(),
        ]);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('core.sessions.manage_all');
        Database::instance()->delete('sessions', ['id' => $id]);
        Session::flash('success', 'ยุติเซสชันเรียบร้อยแล้ว');
        return $this->redirect('/sessions');
    }
}