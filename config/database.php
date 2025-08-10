<?php

/**
 * 数据库配置文件
 * 用于权限系统的数据库连接配置
 */

return [
    // 默认数据库连接标识
    'default' => 'mysql',
    
    // 数据库连接信息
    'connections' => [
        'mysql' => [
            // 数据库类型
            'type' => 'mysql',
            // 服务器地址
            'hostname' => '127.0.0.1',
            // 数据库名
            'database' => 'theadmin',
            // 数据库用户名
            'username' => 'root',
            // 数据库密码
            'password' => '123456',
            // 数据库连接端口
            'hostport' => '3306',
            // 数据库连接参数
            'params' => [
                // 连接超时3秒
                \PDO::ATTR_TIMEOUT => 3,
            ],
            // 数据库编码
            'charset' => 'utf8mb4',
            // 数据库表前缀
            'prefix' => '',
            // 断线重连
            'break_reconnect' => true,
            // 自定义分页类
            'bootstrap' => '',
            // 连接池配置
            'pool' => [
                'max_connections' => 5, // 最大连接数
                'min_connections' => 1, // 最小连接数
                'wait_timeout' => 3,    // 从连接池获取连接等待超时时间
                'idle_timeout' => 60,   // 连接最大空闲时间，超过该时间会被回收
                'heartbeat_interval' => 50, // 心跳检测间隔，需要小于60秒
            ],
        ],
    ],
    
    // 是否开启SQL日志记录
    'sql_log' => false,
    
    // 是否开启慢查询日志
    'slow_log' => true,
    
    // 慢查询时间阈值（秒）
    'slow_threshold' => 2,
];