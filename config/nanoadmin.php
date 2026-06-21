<?php

/**
 * NanoAdmin 业务配置
 *
 * 集中管理中间件、JWT、缓存等业务可配置项。
 * 与 Webman 框架自身的 config/middleware.php（中间件类列表）区分开：
 *   - config/middleware.php     → Webman 要求的中间件类列表
 *   - config/nanoadmin.php      → 本文件，业务中间件读取这里
 */

return [
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
    'auth' => [
        // 不需要认证的路由（前缀匹配）
        'exclude_routes' => [
            '/sys/auth/login',
            '/sys/auth/refresh',
            '/sys/install',
            // Swagger UI 和 OpenAPI 文档不需要认证
            '/sys/openapi',
            '/sys/openapi/doc',
        ],

        // 登录失败时是否记录到登录日志
        'record_failed_login' => true,
    ],

    // 操作日志中间件
    'log_operation' => [
        // 不记录操作日志的路由（前缀匹配）
        'exclude_routes' => [
            '/sys/auth/login',
            '/sys/auth/refresh',
            '/sys/auth/check',
            '/sys/menu/route',
            '/sys/auth/info',
            '/sys/auth/permissions',
            '/sys/auth/menus',
            // Swagger UI 和 OpenAPI 文档不记录日志
            '/sys/openapi',
            '/sys/openapi/doc',
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
        'exclude_routes' => [
            '/sys/auth/login',
            '/sys/auth/logout',
            '/sys/auth/refresh',
            '/sys/auth/info',
            '/sys/auth/permissions',
            '/sys/auth/menus',
            '/sys/menu/route',
            '/sys/install',
            // Swagger UI 和 OpenAPI 文档不需要权限验证
            '/sys/openapi',
            '/sys/openapi/doc',
        ],

        // 超级管理员角色代码（命中其一即放行所有权限）
        'super_admin_roles' => ['R_SUPER', 'super_admin', 'administrator'],

        // 路由 -> 权限代码 映射
        //   * 表示匹配单段路径，METHOD:/path 用于按 HTTP 方法区分
        'route_permissions' => [
            // 管理员管理
            'GET:/sys/admin'            => 'sys:admin:page',
            'POST:/sys/admin'           => 'sys:admin:create',
            'GET:/sys/admin/*'          => 'sys:admin:view',
            'PUT:/sys/admin/*'          => 'sys:admin:update',
            'POST:/sys/admin/*/roles'   => 'sys:admin:assign-role',

            // 角色管理
            'GET:/sys/role'                => 'sys:role:page',
            'POST:/sys/role'               => 'sys:role:create',
            'GET:/sys/role/*'              => 'sys:role:view',
            'PUT:/sys/role/*'              => 'sys:role:update',
            'POST:/sys/role/*/permissions' => 'sys:role:assign-permission',
            'POST:/sys/role/*/menus'       => 'sys:role:assign-menu',

            // 权限管理
            'GET:/sys/permissions'    => 'sys:permission:page',
            'POST:/sys/permissions'   => 'sys:permission:create',
            'GET:/sys/permissions/*'  => 'sys:permission:view',
            'PUT:/sys/permissions/*'  => 'sys:permission:update',

            // 字典类型
            'GET:/sys/dict-type'         => 'sys:dict:type:page',
            'POST:/sys/dict-type'        => 'sys:dict:type:create',
            'PUT:/sys/dict-type/*'       => 'sys:dict:type:update',
            'DELETE:/sys/dict-type/batch' => 'sys:dict:type:delete',
            'DELETE:/sys/dict-type/*'     => 'sys:dict:type:delete',

            // 字典数据
            'GET:/sys/dict-data'         => 'sys:dict:type:page',
            'POST:/sys/dict-data'        => 'sys:dict:type:create',
            'PUT:/sys/dict-data/*'       => 'sys:dict:type:update',
            'DELETE:/sys/dict-data/batch' => 'sys:dict:type:delete',
            'DELETE:/sys/dict-data/*'     => 'sys:dict:type:delete',

            // 文件管理
            'GET:/sys/files'             => 'sys:file:list',
            'POST:/sys/files'            => 'sys:file:create',
            'POST:/sys/files/batch'      => 'sys:file:create',
            'GET:/sys/files/stats'       => 'sys:file:list',
            'GET:/sys/files/*/download'  => 'sys:file:list',
            'GET:/sys/files/*'           => 'sys:file:list',
            'PUT:/sys/files/*'           => 'sys:file:update',
            'DELETE:/sys/files/batch'    => 'sys:file:delete',
            'DELETE:/sys/files/*'        => 'sys:file:delete',

            // 配置管理
            'GET:/sys/config'         => 'sys:config:page',
            'GET:/sys/config/group'   => 'sys:config:page',
            'POST:/sys/config'        => 'sys:config:create',
            'PUT:/sys/config/batch'   => 'sys:config:update',
            'GET:/sys/config/*'       => 'sys:config:page',
            'PUT:/sys/config/*'       => 'sys:config:update',
            'DELETE:/sys/config/batch' => 'sys:config:delete',
            'DELETE:/sys/config/*'     => 'sys:config:delete',

            // 登录日志
            'GET:/sys/login-log'    => 'sys:log:page',
            'GET:/sys/login-log/*'  => 'sys:log:page',

            // 操作日志
            'GET:/sys/operation-log'   => 'sys:log:page',
            'GET:/sys/operation-log/*' => 'sys:log:page',
        ],
    ],
];