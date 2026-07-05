<?php

/**
 * 权限系统配置文件
 */

return [
    // JWT配置
    'jwt' => [
        // JWT密钥
        'secret' => 'nanoadmin_jwt_secret_key_2024',
        // 过期时间（秒）
        'expire' => 7200, // 2小时
        // 刷新时间（秒）
        'refresh' => 86400, // 24小时
        // 算法
        'algorithm' => 'HS256',
        // 发行者
        'issuer' => 'nanoadmin',
        // 受众
        'audience' => 'nanoadmin-admin',
    ],

    // 权限配置
    'permission' => [
        // 超级管理员角色代码
        'super_admin_role' => 'super_admin',
        // 默认权限（无需验证的接口）
        'default_permissions' => [
            'auth.login',
            'auth.logout',
            'auth.profile',
        ],
        // 权限缓存时间（秒）
        'cache_expire' => 3600, // 1小时
        // 权限缓存前缀
        'cache_prefix' => 'nanoadmin:permission:',
    ],

    // 菜单配置
    'menu' => [
        // 菜单缓存时间（秒）
        'cache_expire' => 3600, // 1小时
        // 菜单缓存前缀
        'cache_prefix' => 'nanoadmin:menu:',
        // 默认菜单图标
        'default_icon' => 'Document',
    ],

    // 安全配置
    'security' => [
        // 密码最小长度
        'password_min_length' => 6,
        // 登录失败最大次数
        'max_login_attempts' => 5,
        // 登录失败锁定时间（秒）
        'login_lock_time' => 900, // 15分钟
        // 是否记录操作日志
        'enable_operation_log' => true,
    ],

    // 分页配置
    'pagination' => [
        // 默认每页数量
        'default_limit' => 15,
        // 最大每页数量
        'max_limit' => 100,
    ],

    // 上传配置
    'upload' => [
        // 头像上传路径
        'avatar_path' => 'uploads/avatar/',
        // 允许的头像文件类型
        'avatar_types' => ['jpg', 'jpeg', 'png', 'gif'],
        // 头像文件最大大小（字节）
        'avatar_max_size' => 2 * 1024 * 1024, // 2MB
    ],

    // API配置
    'api' => [
        // API版本
        'version' => 'v1',
        // API前缀
        'prefix' => 'api',
        // 跨域配置
        'cors' => [
            'allow_origin' => '*',
            'allow_methods' => 'GET,POST,PUT,DELETE,OPTIONS',
            'allow_headers' => 'Content-Type,Authorization,X-Requested-With',
        ],
    ],

    // 日志配置
    'log' => [
        // 是否启用日志
        'enable' => true,
        // 日志级别
        'level' => 'info',
        // 日志文件路径
        'path' => runtime_path() . '/logs/permission/',
        // 日志文件名格式
        'filename' => 'permission-{date}.log',
        // 日志保留天数
        'max_days' => 30,
    ],
];