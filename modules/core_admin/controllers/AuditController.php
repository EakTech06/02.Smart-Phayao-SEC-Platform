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
        $module = is_string($module) ? mb_substr(trim($module), 0, 50) : '';
        $event = is_string($event) ? mb_substr(trim($event), 0, 100) : '';

        $sql = 'SELECT al.id, al.user_id, al.action, al.module, al.entity_type, al.entity_id,
                       al.ip_address, al.created_at, u.username
                FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE 1=1';
        $params = [];

        if ($module !== '') {
            $sql .= ' AND al.module = ?';
            $params[] = $module;
        }
        if ($event !== '') {
            // ชื่อเหตุการณ์เก็บในคอลัมน์ action (เช่น user.created) — escape wildcard ของ LIKE
            $sql .= " AND al.action LIKE ? ESCAPE '!'";
            $params[] = '%' . strtr($event, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
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