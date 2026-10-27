<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers\Api;

use Core\Auth;
use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Rbac;
use Core\Response;
use Modules\CoreAdmin\Models\UserModel;

final class UserApiController extends Controller
{
    public function me(): Response
    {
        $user = Auth::user() ?? throw new HttpException(401);

        return $this->success([
            'user' => UserModel::toPublic(UserModel::findDetail((int) $user['id']) ?? []),
            'roles' => array_column(Rbac::roles(), 'code'),
            'permissions' => array_keys(Rbac::permissions()),
        ]);
    }

    public function index(): Response
    {
        $users = array_map([UserModel::class, 'toPublic'], UserModel::listVisible());

        return $this->success(['users' => $users, 'total' => count($users)]);
    }

    public function show(string $id): Response
    {
        $user = UserModel::findVisible((int) $id) ?? throw new HttpException(404, 'ไม่พบผู้ใช้งาน');

        return $this->success(['user' => UserModel::toPublic($user)]);
    }
}