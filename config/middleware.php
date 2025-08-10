<?php

use plugin\theadmin\app\middleware\AuthMiddleware;
use plugin\theadmin\app\middleware\PermissionMiddleware;

return [
    // 全局中间件
    '' => [
        // 认证中间件 - 验证JWT Token
        AuthMiddleware::class,
        // 权限中间件 - 验证用户权限
        PermissionMiddleware::class,
    ],
    
    // 路由组中间件示例
    'api' => [
        AuthMiddleware::class,
        PermissionMiddleware::class,
    ],
    
    // 管理员相关路由中间件
    'admin' => [
        AuthMiddleware::class,
        PermissionMiddleware::class,
    ],
];