<?php

declare(strict_types=1);

use plugin\nanoadmin\app\command\ClearReflectCache;
use plugin\nanoadmin\app\command\ScanPermissionsCommand;

/**
 * 注册 nanoadmin 插件的命令
 *
 * 部署后清空反射缓存：
 *   php console cache:clear-reflect
 *
 * 扫描权限缺口（CI 防退化）：
 *   php console scan:permissions            详细报告
 *   php console scan:permissions --missing  仅列出缺失项
 *   php console scan:permissions --check    CI 模式：缺失时 exit 1
 *
 * 来源：authorization-refactoring-plan.md §2.9.6 + §3.2
 */
return [
    ClearReflectCache::class,
    ScanPermissionsCommand::class,
];