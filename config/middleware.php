<?php

use plugin\theadmin\app\middleware\AuthMiddleware;
use plugin\theadmin\app\middleware\CorsMiddleware;
use plugin\theadmin\app\middleware\LogOperationMiddleware;
use plugin\theadmin\app\middleware\PermissionMiddleware;

return [
    // 全局中间件
    '' => [
        CorsMiddleware::class,
        AuthMiddleware::class,
        LogOperationMiddleware::class,
        // PermissionMiddleware::class,
    ],
];
