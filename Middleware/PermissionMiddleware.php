<?php

declare(strict_types=1);

namespace Middleware;

use Core\Exceptions\HttpException;
use Core\MiddlewareInterface;
use Core\Rbac;
use Core\Request;
use Core\Response;

/** ใช้: 'perm:core.users.read_all,core.users.read_department' (มีสิทธิ์ใดสิทธิ์หนึ่งก็ผ่าน) */
final class PermissionMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $permissions;

    public function __construct(string ...$permissions)
    {
        $this->permissions = array_values($permissions);
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!Rbac::canAny($this->permissions)) {
            throw new HttpException(403);
        }

        return $next($request);
    }
}