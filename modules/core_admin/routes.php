<?php

declare(strict_types=1);

use Core\Router;
use Modules\CoreAdmin\Controllers\Api\UserApiController;
use Modules\CoreAdmin\Controllers\AuthController;
use Modules\CoreAdmin\Controllers\DashboardController;
use Modules\CoreAdmin\Controllers\UserController;

/** @var Router $router */

$userRead = 'perm:core.users.read_all,core.users.read_service_unit,core.users.read_department,core.users.read_own';
$userUpdate = 'perm:core.users.update_all,core.users.update_department';

$router->get('/', [DashboardController::class, 'home']);

$router->group(['middleware' => ['guest']], static function (Router $r): void {
    $r->get('/login', [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login'], ['csrf']);
});

$router->group(['middleware' => ['guest', '2fa.pending']], static function (Router $r): void {
    $r->get('/2fa', [AuthController::class, 'showTwoFactor']);
    $r->post('/2fa', [AuthController::class, 'verifyTwoFactor'], ['csrf']);
    $r->get('/2fa/setup', [AuthController::class, 'showTwoFactorSetup']);
    $r->post('/2fa/setup', [AuthController::class, 'confirmTwoFactorSetup'], ['csrf']);
});

$router->group(['middleware' => ['auth']], static function (Router $r): void {
    $r->post('/logout', [AuthController::class, 'logout'], ['csrf']);
    $r->get('/password/change', [AuthController::class, 'showChangePassword']);
    $r->post('/password/change', [AuthController::class, 'changePassword'], ['csrf']);
});

$router->group(['middleware' => ['auth', 'password.fresh']], static function (Router $r) use ($userRead, $userUpdate): void {
    $r->get('/dashboard', [DashboardController::class, 'index'], ['perm:core.dashboard.view']);

    $r->get('/users', [UserController::class, 'index'], [$userRead]);
    $r->get('/users/create', [UserController::class, 'create'], ['perm:core.users.create_all']);
    $r->post('/users', [UserController::class, 'store'], ['csrf', 'perm:core.users.create_all']);
    $r->get('/users/{id:\d+}', [UserController::class, 'show'], [$userRead]);
    $r->get('/users/{id:\d+}/edit', [UserController::class, 'edit'], [$userUpdate]);
    $r->put('/users/{id:\d+}', [UserController::class, 'update'], ['csrf', $userUpdate]);
    $r->post('/users/{id:\d+}/status', [UserController::class, 'toggleStatus'], ['csrf', $userUpdate]);
    $r->post('/users/{id:\d+}/reset-password', [UserController::class, 'resetPassword'], ['csrf', $userUpdate]);
    $r->delete('/users/{id:\d+}', [UserController::class, 'destroy'], ['csrf', 'perm:core.users.delete_all']);
});

$router->group(['prefix' => '/api/v1', 'middleware' => ['auth', 'password.fresh']], static function (Router $r) use ($userRead): void {
    $r->get('/me', [UserApiController::class, 'me']);
    $r->get('/users', [UserApiController::class, 'index'], [$userRead]);
    $r->get('/users/{id:\d+}', [UserApiController::class, 'show'], [$userRead]);
});

// Roles & Matrix
$router->get('/roles', [Modules\CoreAdmin\Controllers\RoleController::class, 'index'], ['perm:core.roles.read_all']);
$router->post('/roles/{id:\d+}/matrix', [Modules\CoreAdmin\Controllers\RoleController::class, 'updateMatrix'], ['csrf', 'perm:core.roles.update_all']);

// User Permissions Override
$router->get('/users/{id:\d+}/permissions', [Modules\CoreAdmin\Controllers\UserPermissionController::class, 'edit'], ['perm:core.permissions.assign_all']);
$router->post('/users/{id:\d+}/permissions', [Modules\CoreAdmin\Controllers\UserPermissionController::class, 'store'], ['csrf', 'perm:core.permissions.assign_all']);
//$router->delete('/users/{id:\d+}/permissions/{permId:\d+}', [Modules\CoreAdmin\Controllers\UserPermissionController::class, 'destroy'], ['csrf', 'perm:core.permissions.assign_all']);    // Audit Logs
$router->post('/users/{id:\d+}/permissions/delete', [Modules\CoreAdmin\Controllers\UserPermissionController::class, 'destroy'], ['csrf', 'perm:core.permissions.assign_all']);

$router->get('/audit-logs', [Modules\CoreAdmin\Controllers\AuditController::class, 'index'], ['perm:core.audit.read_all']);

// Settings
$router->get('/settings', [Modules\CoreAdmin\Controllers\SettingController::class, 'index'], ['perm:core.settings.manage']);
$router->post('/settings', [Modules\CoreAdmin\Controllers\SettingController::class, 'update'], ['csrf', 'perm:core.settings.manage']);

// Sessions Management
$router->get('/sessions', [Modules\CoreAdmin\Controllers\SessionController::class, 'index'], ['perm:core.sessions.read_all']);
$router->delete('/sessions/{id}', [Modules\CoreAdmin\Controllers\SessionController::class, 'destroy'], ['csrf', 'perm:core.sessions.manage_all']);


// Profile
$router->get('/profile', [Modules\CoreAdmin\Controllers\ProfileController::class, 'index']);
$router->post('/profile', [Modules\CoreAdmin\Controllers\ProfileController::class, 'update'], ['csrf']);
$router->post('/profile/password', [Modules\CoreAdmin\Controllers\ProfileController::class, 'updatePassword'], ['csrf']);
