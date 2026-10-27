<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpException;

abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    protected function view(string $view, array $data = [], ?string $layout = 'layout'): Response
    {
        return Response::html(View::render($view, $data, $layout));
    }

    protected function validate(array $rules, array $labels = []): array
    {
        return Validator::validate($this->request->all(), $rules, $labels);
    }

    protected function authorize(string ...$permissions): void
    {
        if (!Rbac::canAny($permissions)) {
            throw new HttpException(403);
        }
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function success(mixed $data = null, int $status = 200): Response
    {
        return Response::success($data, $status);
    }

    protected function fail(array $data, int $status = 400): Response
    {
        return Response::fail($data, $status);
    }
}