<?php

$redisConfigFile = base_path() . '/config/redis.php';
$redisDefault    = file_exists($redisConfigFile) ? ((require $redisConfigFile)['default'] ?? []) : [];

return [
    'default' => $redisDefault ? 'redis' : 'file',

    'stores' => [
        'file' => [
            'type'       => 'File',
            'path'       => runtime_path('cache'),
            'prefix'     => 'nanoadmin_',
            'expire'     => 0,
            'tag_prefix' => 'tag:',
            'serialize'  => ['serialize', 'unserialize'],
        ],

        'redis' => [
            'type'       => 'redis',
            'expire'     => 0,
            'tag_prefix' => 'tag:',
            'serialize'  => ['serialize', 'unserialize'],
            ...$redisDefault,
        ],
    ],
];