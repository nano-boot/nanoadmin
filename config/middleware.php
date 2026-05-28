<?php

use plugin\theadmin\app\middleware\AuthMiddleware;
use plugin\theadmin\app\middleware\CorsMiddleware;
use plugin\theadmin\app\middleware\OperationLogMiddleware;
use plugin\theadmin\app\middleware\PermissionMiddleware;

return [
    // 全局中间件
    '' => [
        CorsMiddleware::class,
        AuthMiddleware::class,
        OperationLogMiddleware::class,
        // PermissionMiddleware::class,
    ],
];