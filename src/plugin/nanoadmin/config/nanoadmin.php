<?php

/**
 * NanoAdmin 业务配置
 *
 * 集中管理中间件、JWT、缓存等业务可配置项。
 * 与 Webman 框架自身的 config/middleware.php（中间件类列表）区分开：
 *   - config/middleware.php     → Webman 要求的中间件类列表
 *   - config/nanoadmin.php      → 本文件，业务中间件读取这里
 *
 * Swagger / OpenAPI 文档相关配置已抽到 plugin/nanoadmin/config/swagger.php，
 * 三个中间件（auth / log_operation / permission）的 exclude_routes 会自动从那里
 * 同步注入 ui_route / doc_route，无需再在此处重复维护。
 */

return [
    // ============================================================
    // exclude_routes 共享池（Phase 1 改造）
    // ============================================================
    // permission 和 log_operation 共用，避免跨中间件分散维护
    // 格式：'@no_permission_routes' 在 BaseMiddleware 中展开
    // 注意：auth.exclude_routes 不引用此池（语义不同：免登录 vs 免权限）
    'no_permission_routes' => [
        // 平台路由（由 BaseMiddleware 自动注入，运营改不动）
        '/',
        '/install',
        '/sys/install',
        '/sys/openapi',
        '/sys/openapi/doc',

        // 认证相关（已登录但免权限）
        '/sys/auth/info',
        '/sys/auth/permissions',
        '/sys/auth/menus',
        '/sys/menu/route',

        // 自操作接口（已登录但免权限，个人中心场景）
        '/sys/admin/password',
        '/sys/admin/info',

        // 完全匿名（免登录 + 免权限 + 免日志）
        '/sys/auth/login',
        '/sys/auth/refresh',
        '/sys/auth/captcha',
        '/sys/auth/check',
        '/sys/auth/logout',
    ],

    // 跨域中间件
    // 跨域中间件
    'cors' => [
        // 是否启用 CORS
        'enabled' => true,

        // 允许的来源；可填具体域名 ['https://example.com']，'*' 表示任意
        'allow_origin' => '*',

        // 允许的 HTTP 方法
        'allow_methods' => 'GET,POST,PUT,DELETE,OPTIONS,PATCH',

        // 允许的请求头
        'allow_headers' => 'Content-Type,Authorization,X-Requested-With,Accept,Origin,User-Agent,DNT,Cache-Control,X-Mx-ReqToken,Keep-Alive,X-CustomHeader',

        // 是否允许携带凭证
        'allow_credentials' => 'true',

        // 预检请求缓存秒数
        'max_age' => 86400,
    ],

    // 认证中间件
    // 不需要认证的路由通过 @no_permission_routes 共享池管理
    'auth' => [
        // 完全匿名路由白名单
        'exclude_routes' => [],

        // 登录失败时是否记录到登录日志
        'record_failed_login' => true,
    ],

    // 操作日志中间件
    'log_operation' => [
        // 不记录操作日志的路由（前缀匹配）
        // @no_permission_routes 引用共享池，BaseMiddleware 自动展开
        'exclude_routes' => [
            '@no_permission_routes',
            // 追加只对"日志"敏感的高频轮询接口
            '/sys/notification/poll',
        ],

        // 不记录操作日志的 HTTP 方法
        'exclude_methods' => ['OPTIONS'],

        // 请求参数中需要脱敏的字段（不区分大小写）
        'sensitive_keys' => [
            'password',
            'password_confirm',
            'old_password',
            'new_password',
            'token',
            'secret',
        ],
    ],

    // 权限验证中间件
    'permission' => [
        // 不需要权限验证的路由（前缀匹配）
        // @no_permission_routes 引用共享池，BaseMiddleware 自动展开
        'exclude_routes' => '@no_permission_routes',

        // 超级管理员角色代码（命中其一即放行所有权限）
        'super_admin_roles' => ['R_SUPER', 'super_admin', 'administrator'],

        // Phase 2 P0 完成（authorization-refactoring-plan.md §2.9 第 6 步）：
        // route_permissions 已废弃，权限码通过 #[Permission] 注解 + PermissionInferrer 自动推断
        // 获取，再无手动配置入口。
    ],

    // ============================
    // 缓存相关业务配置
    // 原 plugin/nanoadmin/config/cache.php 中的业务模块配置（dict / menu / cleanup / monitoring）
    // 框架级 default/stores 配置保留在 config/cache.php 由 think-cache 框架读取。
    // ============================
    'cache' => [
        // 字典缓存配置
        'dict' => [
            // 是否启用缓存
            'enabled' => true,

            // 缓存键前缀（think-cache 仅禁用 ";"，":" 可正常使用，与 Redis 命名空间惯例一致）
            'prefix' => 'dict:',

            // 缓存 TTL（秒），0 表示无过期（依赖 CRUD 末尾 clearCache 保证一致性）
            'ttl' => 0,

            // 可选：指定缓存 store（留空则用 config/think-cache.php 中的 default）
            // 例如：'array' 用于单进程内存缓存；'redis' 用于跨进程/跨服务器共享
            'store' => null,

            // 是否启用启动预热
            'warmup_enabled' => true,

            // 预热策略：指定编码则只预热这些，空数组则预热全部
            'warmup_codes' => [],
        ],

        // 菜单缓存配置
        'menu' => [
            // 默认缓存时间（秒）
            'ttl' => 3600, // 1小时

            // 缓存键前缀
            'prefix' => 'menu:',

            // 是否启用缓存
            'enabled' => true,

            // 预热缓存的常用角色
            'warmup_roles' => [
                ['admin'],
                ['user'],
                ['admin', 'user'],
                ['manager'],
            ],

            // 缓存策略配置
            'strategies' => [
                // 路由配置缓存
                'route_config' => [
                    'enabled' => true,
                    'ttl' => 3600,
                ],

                // 权限过滤缓存
                'role_filter' => [
                    'enabled' => true,
                    'ttl' => 1800, // 30分钟
                ],

                // 选择器数据缓存
                'selector_data' => [
                    'enabled' => true,
                    'ttl' => 7200, // 2小时
                ],

                // 权限列表缓存
                'permissions' => [
                    'enabled' => true,
                    'ttl' => 3600,
                ],

                // 统计信息缓存
                'statistics' => [
                    'enabled' => true,
                    'ttl' => 1800,
                ],
            ],
        ],

        // 缓存清理配置
        'cleanup' => [
            // 是否启用自动清理过期缓存
            'auto_cleanup' => true,

            // 清理间隔（秒）
            'cleanup_interval' => 3600,

            // 最大缓存文件数量（仅文件缓存）
            'max_files' => 1000,
        ],

        // 缓存监控配置
        'monitoring' => [
            // 是否启用缓存统计
            'enabled' => true,

            // 统计信息保存时间（秒）
            'stats_ttl' => 86400, // 24小时
        ],
    ],
];