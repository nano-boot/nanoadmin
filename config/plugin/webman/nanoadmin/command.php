<?php

declare(strict_types=1);

use plugin\nanoadmin\app\command\ClearReflectCache;

/**
 * 注册 nanoadmin 插件的命令
 *
 * 部署后清空反射缓存：
 *   php console cache:clear-reflect
 *
 * 来源：authorization-refactoring-plan.md §2.9.6
 */
return [
    ClearReflectCache::class,
];