<?php

declare(strict_types=1);

use Core\Router;
use Modules\CoreAdmin\Controllers\Api\UserApiController;
use Modules\CoreAdmin\Controllers\AuthController;
use Modules\CoreAdmin\Controllers\DashboardController;
use Modules\CoreAdmin\Controllers\UserController;

/** @var Router $router */

$userRead = 'perm:core.users.read_all,core.users.read_service_unit,core.users.read_department,core.users.read_own';

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

$router->group(['middleware' => ['auth', 'password.fresh']], static function (Router $r) use ($userRead): void {
    $r->get('/dashboard', [DashboardController::class, 'index'], ['perm:core.dashboard.view']);
    $r->get('/users', [UserController::class, 'index'], [$userRead]);
    $r->get('/users/{id:\d+}', [UserController::class, 'show'], [$userRead]);

   // เพิ่ม 2 บรรทัดนี้ไว้นอกเหนือ password.fresh เพื่อให้ผู้ใช้ที่ติดบังคับเปลี่ยนรหัสผ่านเข้าถึงได้
    $r->get('/password/change', [AuthController::class, 'showChangePassword']);
    $r->post('/password/change', [AuthController::class, 'changePassword'], ['csrf']);

});

$router->group(['prefix' => '/api/v1', 'middleware' => ['auth', 'password.fresh']], static function (Router $r) use ($userRead): void {
    $r->get('/me', [UserApiController::class, 'me']);
    $r->get('/users', [UserApiController::class, 'index'], [$userRead]);
    $r->get('/users/{id:\d+}', [UserApiController::class, 'show'], [$userRead]);
});