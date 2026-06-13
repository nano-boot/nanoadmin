<?php

/**
 * 安装向导配置
 */

return [
    // 是否允许重新安装
    'allow_reinstall' => false,

    // 安装向导访问密钥（防止未授权访问）
    'access_key' => '',

    // 默认数据库配置
    'defaults' => [
        'hostname' => '127.0.0.1',
        'hostport' => '3306',
        'database' => 'nanoadmin',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'prefix' => 'th_',
    ],

    // 管理员默认配置
    'admin' => [
        'username' => 'admin',
        'nickname' => '超级管理员',
    ],

    // PHP 环境要求
    'requirements' => [
        'php_version' => '8.1.0',
        'extensions' => [
            'pdo',
            'pdo_mysql',
            'mbstring',
            'openssl',
            'json',
            'fileinfo',
        ],
    ],
];
