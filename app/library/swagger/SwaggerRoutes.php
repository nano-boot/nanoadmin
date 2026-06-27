<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\library\swagger;

/**
 * Swagger / OpenAPI 路由辅助
 *
 * 集中暴露 swagger 配置中声明的两个路由（UI 与 doc），
 * 供 AuthMiddleware / LogOperationMiddleware / PermissionMiddleware 等
 * 读取"应排除"路由时统一引用，避免硬编码两份路径漂移。
 *
 * 当 swagger.php 中 enabled = false 时返回空数组，表示没有任何路由需要排除。
 */
final class SwaggerRoutes
{
    /**
     * 返回当前 swagger 配置中应被中间件白名单排除的路由列表
     *
     * @return string[]
     */
    public static function excludeRoutes(): array
    {
        if (!function_exists('config')) {
            return [];
        }

        $config = (array) config('plugin.nanoadmin.swagger', []);
        if (empty($config['enabled'])) {
            return [];
        }

        $routes = [];
        foreach (['ui_route', 'doc_route'] as $key) {
            if (!empty($config[$key]) && is_string($config[$key])) {
                $routes[] = $config[$key];
            }
        }
        return $routes;
    }
}