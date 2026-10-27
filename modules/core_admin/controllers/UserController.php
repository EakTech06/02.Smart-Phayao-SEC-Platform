<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Rbac;
use Core\Response;
use Modules\CoreAdmin\Models\UserModel;

final class UserController extends Controller
{
    public function index(): Response
    {
        return $this->view('core_admin::users/index', [
            'title' => 'ผู้ใช้งาน',
            'users' => UserModel::listVisible(),
            'scope' => Rbac::scope('core.users', 'read'),
        ]);
    }

    public function show(string $id): Response
    {
        $user = UserModel::findVisible((int) $id) ?? throw new HttpException(404);

        return $this->view('core_admin::users/show', ['title' => 'ข้อมูลผู้ใช้งาน', 'user' => $user]);
    }
}