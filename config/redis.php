<?php
return [
    'default' => [
        'host'     => '127.0.0.1',
        'username' => null,
        'password' => null,
        'port'     => 6379,
        'database' => 12,
        'prefix'   => 'nanoadmin:',
        'pool' => [ // 连接池配置
            'max_connections' => 10,     // 连接池最大连接数
            'min_connections' => 1,      // 连接池最小连接数
            'wait_timeout' => 3,         // 从连接池获取连接最大等待时间
            'idle_timeout' => 50,        // 连接池中连接空闲超时时间，超过该时间会被关闭，直到连接数为min_connections
            'heartbeat_interval' => 50,  // 心跳检测间隔，不要大于60秒
        ],
    ]
];