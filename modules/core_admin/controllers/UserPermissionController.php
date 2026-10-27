<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Models\UserModel;
use Modules\CoreAdmin\Services\UserPermissionService;
use Modules\CoreAdmin\Services\RolePermissionService;

final class UserPermissionController extends Controller
{
    public function edit(string $id): Response
    {
        $this->authorize('core.permissions.assign_all');
        $user = UserModel::findVisible((int) $id) ?? throw new HttpException(404);

        return $this->view('core_admin::users/permissions', [
            'title' => 'จัดการสิทธิ์รายบุคคล',
            'user' => $user,
            'overrides' => UserPermissionService::overrides((int) $user['id']),
            'grouped' => RolePermissionService::groupedPermissions(),
        ]);
    }

    public function store(string $id): Response
    {
        $this->authorize('core.permissions.assign_all');
        $user = UserModel::findVisible((int) $id) ?? throw new HttpException(404);

        $data = $this->validate([
            'permission_id' => 'required|integer',
            'effect' => 'required|in:allow,deny',
            'expires_at' => 'nullable|date',
            'reason' => 'nullable|max:255',
        ]);

        UserPermissionService::saveOverride(
            (int) $user['id'],
            (int) $data['permission_id'],
            (string) $data['effect'],
            (string) ($data['expires_at'] ?? ''),
            (string) ($data['reason'] ?? '')
        );

        Session::flash('success', 'บันทึกสิทธิ์รายบุคคลเรียบร้อยแล้ว');
        return $this->redirect('/users/' . (int) $user['id'] . '/permissions');
    }

    public function destroy(string $id, string $permId): Response
    {
        $this->authorize('core.permissions.assign_all');
        $user = UserModel::findVisible((int) $id) ?? throw new HttpException(404);

        UserPermissionService::removeOverride((int) $user['id'], (int) $permId);
        Session::flash('success', 'ลบสิทธิ์เฉพาะตัวเรียบร้อยแล้ว');
        return $this->redirect('/users/' . (int) $user['id'] . '/permissions');
    }
}