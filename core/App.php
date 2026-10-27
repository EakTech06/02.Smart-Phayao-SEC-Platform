<?php

declare(strict_types=1);

namespace Core;

final class App
{
    public function run(): void
    {
        $request = Request::capture();
        try {
            Session::start();
            $response = $this->buildRouter()->dispatch($request);
        } catch (\Throwable $e) {
            $response = ErrorHandler::toResponse($e, $request);
        }
        $response->send();
    }

    /** HMVC: แต่ละ module ลงทะเบียน route ของตัวเองผ่าน modules/{module}/routes.php */
    private function buildRouter(): Router
    {
        $router = new Router();
        foreach ((array) Config::get('app.modules', []) as $module) {
            if (!is_string($module) || !preg_match('/^[a-z0-9_]+$/', $module)) {
                continue;
            }
            $file = BASE_PATH . '/modules/' . $module . '/routes.php';
            if (is_file($file)) {
                (static function (Router $router, string $__file): void {
                    require $__file;
                })($router, $file);
            }
        }

        return $router;
    }
}