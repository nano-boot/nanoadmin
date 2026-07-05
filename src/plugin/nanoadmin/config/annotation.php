<?php

/**
 * NanoAdmin 反射缓存配置（Phase 2 新增）
 *
 * 配置 plugin.nanoadmin.annotation 命名空间，
 * 供 ReflectionCache::config() 在运行时读取。
 *
 * tag / key 的环境前缀（{$env}）由 ReflectionCache::config() 在运行时叠加，
 * 这样切换环境不需要修改配置；同时也避免 dev/staging/prod 共用 Redis 时串扰。
 *
 * 字段说明：
 *  - tag           所有反射缓存的 tag 名称（部署时 cache:clear-reflect 清空）
 *  - expire        缓存 TTL（秒），默认 1 年
 *  - permission    方法级 #[Permission] 注解 key 前缀
 *  - class         类级 #[Permission] 注解 key 前缀
 *  - anonymous     #[AllowAnonymous] 注解 key 前缀
 *  - no_need_login 兼容 saiadmin $noNeedLogin 属性 key 前缀
 *
 * 来源：authorization-refactoring-plan.md §2.9.3
 */
return [
    'reflection_cache' => [
        'tag'           => 'nanoadmin:reflection',
        'expire'        => 60 * 60 * 24 * 365, // 1 年
        'permission'    => 'nanoadmin:reflection:permission_',
        'class'         => 'nanoadmin:reflection:class_',
        'anonymous'     => 'nanoadmin:reflection:anonymous_',
        'no_need_login' => 'nanoadmin:reflection:no_need_login_',
    ],
];