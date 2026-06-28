<?php

use plugin\nanoadmin\app\middleware\InstallGuard;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\CorsMiddleware;
use plugin\nanoadmin\app\middleware\LogOperationMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;

/**
 * NanoAdmin 中间件注册
 *
 * 这里的键是 Webman 框架识别的"应用/分组"，值是中间件类名列表。
 * - '@' 表示全局中间件（所有请求都会经过）
 * - ''   表示业务默认分组（控制器未指定分组时使用）
 *
 * 如需调整中间件顺序或禁用某个中间件，编辑本文件即可。
 * 业务参数（白名单路由、权限映射等）请编辑 config/nanoadmin.php。
 */
return [
    // 全局中间件
    '@' => [
        InstallGuard::class,
        CorsMiddleware::class,
        AuthMiddleware::class,
        LogOperationMiddleware::class,
        // PermissionMiddleware::class,
    ],

    // 控制器分组中间件（按需启用权限校验中间件）
    'admin' => [
        PermissionMiddleware::class,
    ],
];