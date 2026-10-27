<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Services\RolePermissionService;

final class RoleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('core.roles.read_all');
        $roles = Database::instance()->fetchAll('SELECT * FROM roles ORDER BY level');
        return $this->view('core_admin::roles/matrix', [
            'title' => 'จัดการสิทธิ์บทบาท (Role Matrix)',
            'roles' => $roles,
            'grouped' => RolePermissionService::groupedPermissions(),
            'selectedRoleId' => (int) ($this->request->query('role_id') ?: ($roles[0]['id'] ?? 1)),
        ]);
    }

    public function updateMatrix(string $id): Response
    {
        $this->authorize('core.roles.update_all');
        $roleId = (int) $id;
        $rawPerms = $this->request->input('permissions', []);
        $permIds = is_array($rawPerms) ? array_map('intval', $rawPerms) : [];

        RolePermissionService::updateMatrix($roleId, $permIds);
        Session::flash('success', 'บันทึกสิทธิ์ของบทบาทเรียบร้อยแล้ว');
        return $this->redirect('/roles?role_id=' . $roleId);
    }
}