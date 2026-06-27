<?php

/**
 * NanoAdmin Swagger / OpenAPI 配置
 *
 * 通过 plugin/nanoadmin/config/swagger.php 集中管理 Swagger 文档相关配置：
 *  - enabled          是否启用 Swagger UI 与 OpenAPI 文档路由
 *  - ui_route         Swagger UI 页面路径
 *  - doc_route        OpenAPI YAML 文档接口路径
 *  - info             OpenAPI info 元数据（title / version / description）
 *  - servers          OpenAPI servers 列表
 *  - auto_complete    自动补全配置（如默认 tag）
 *  - scan_path        自定义额外扫描路径（默认 = 控制器目录 + library/swagger + schema）
 *
 * 在 OpenApiBootstrap::register() 中自动读取，无需再传参。
 * 想完全关闭文档时，把 'enabled' 设为 false 即可。
 */

return [
    // 是否启用 Swagger UI 与 OpenAPI 文档路由（关闭后完全不注册任何路由）
    'enabled' => true,

    // Swagger UI 页面路径
    'ui_route' => '/sys/openapi',

    // OpenAPI YAML 文档接口路径
    'doc_route' => '/sys/openapi/doc',

    // OpenAPI info 元数据
    'info' => [
        'title' => 'Nano Admin API',
        'version' => '1.0.0',
        'description' => 'Nano Admin 后台管理系统 API 文档',
    ],

    // OpenAPI servers 列表
    'servers' => [
        ['url' => '/', 'description' => '当前服务'],
    ],

    // 自动补全配置：未显式指定 tag 时使用的默认 tag
    'auto_complete' => [
        'default_tag' => '其他',
    ],

    // 自定义额外扫描路径（可选）
    // 默认会扫描：控制器目录 + plugin/nanoadmin/app/library/swagger + plugin/nanoadmin/app/schema
    // 如需追加其他目录（如业务侧自定义 schema 目录），在此追加绝对路径
    'scan_path' => [],
];