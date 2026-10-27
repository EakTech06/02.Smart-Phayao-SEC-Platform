<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Database;
use Core\Response;

final class AuditController extends Controller
{
    public function index(): Response
    {
        $this->authorize('core.audit.read_all');
        $db = Database::instance();

        $module = $this->request->query('module', '');
        $event = $this->request->query('event', '');

        $sql = 'SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE 1=1';
        $params = [];

        if ($module !== '') {
            $sql .= ' AND al.module = ?';
            $params[] = $module;
        }
        if ($event !== '') {
            // รองรับทั้งคอลัมน์ event และ action (ถ้ามี)
            $sql .= ' AND (al.event LIKE ? OR al.action LIKE ?)';
            $params[] = '%' . $event . '%';
            $params[] = '%' . $event . '%';
        }

        $sql .= ' ORDER BY al.id DESC LIMIT 100';
        $logs = $db->fetchAll($sql, $params);
        
        $modulesRaw = $db->fetchAll('SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL AND module <> "" ORDER BY module');
        $modules = array_column($modulesRaw, 'module');

        return $this->view('core_admin::audit/index', [
            'title' => 'ประวัติการใช้งานระบบ (Audit Logs)',
            'logs' => $logs,
            'modules' => $modules,
            'filterModule' => $module,
            'filterEvent' => $event,
        ]);
    }
}