<?php

/**
 * TheAdmin 缓存配置
 */
return [
    // 缓存类型: redis, file
    'type' => 'redis',
    
    // Redis 配置
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'database' => 1, // 使用数据库1存储菜单缓存
        'timeout' => 2,
        'prefix' => 'theadmin_cache:',
    ],
    
    // 文件缓存配置
    'file' => [
        'path' => runtime_path() . '/cache/theadmin/',
        'prefix' => 'theadmin_cache_',
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
];