<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'http'      => [
        \App\Middleware\Home\AuthMiddleware::class,
        \Hyperf\Validation\Middleware\ValidationMiddleware::class
    ],
    'httpWork'  => [
        \App\Middleware\Work\AuthMiddleware::class,
        \Hyperf\Validation\Middleware\ValidationMiddleware::class
    ],
    'httpAdmin' => [
        \App\Middleware\Admin\AuthMiddleware::class,
        \Hyperf\Validation\Middleware\ValidationMiddleware::class
    ],
    'webSocket' => [
        \App\Middleware\WebSocket\AuthMiddleware::class,
        \Hyperf\Validation\Middleware\ValidationMiddleware::class
    ],
    'api'       => [
        \App\Middleware\Api\AuthMiddleware::class,
        \Hyperf\Validation\Middleware\ValidationMiddleware::class
    ],
];
