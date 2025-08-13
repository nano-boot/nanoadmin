<?php

use plugin\theadmin\app\middleware\AuthMiddleware;
use plugin\theadmin\app\middleware\PermissionMiddleware;

return [

    // 路由组中间件示例
    'theadmin' => [
        AuthMiddleware::class,
        PermissionMiddleware::class,
    ],
];