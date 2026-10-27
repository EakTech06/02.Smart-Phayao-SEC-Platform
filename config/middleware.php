<?php

declare(strict_types=1);

return [
    'aliases' => [
        'auth' => Middleware\AuthMiddleware::class,
        'guest' => Middleware\GuestMiddleware::class,
        'csrf' => Middleware\CsrfMiddleware::class,
        'perm' => Middleware\PermissionMiddleware::class,
        'password.fresh' => Middleware\PasswordFreshMiddleware::class,
        '2fa.pending' => Middleware\TwoFactorPendingMiddleware::class,
    ],
];