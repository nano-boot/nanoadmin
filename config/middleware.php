<?php

use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\CorsMiddleware;
use plugin\nanoadmin\app\middleware\LogOperationMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;

return [
    // 全局中间件
    '' => [
        CorsMiddleware::class,
        AuthMiddleware::class,
        LogOperationMiddleware::class,
        // PermissionMiddleware::class,
    ],
];
