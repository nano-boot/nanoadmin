<?php

/**
 * NanoAdmin 缓存配置
 *
 * 本文件只承载 webman think-cache 框架所需的 default/stores 配置。
 * 业务模块缓存配置（dict / menu / cleanup / monitoring）已合并到
 * plugin/nanoadmin/config/nanoadmin.php 中的 'cache' 键下。
 */

return [
    'default' => 'redis',

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => runtime_path('cache'),
            'prefix' => 'nanoadmin_',
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default'
        ],
        'array' => [
            'driver' => 'array'
        ],
    ],
];
