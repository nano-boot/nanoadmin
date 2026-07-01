<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Request;
use plugin\nanoadmin\app\library\swagger\SwaggerRoutes;

/**
 * 中间件基类（Phase 1 exclude_routes 共享池改造）
 *
 * 统一 exclude_routes 解析逻辑：
 * 1. 支持 @no_permission_routes 引用语法
 * 2. 自动注入平台路由（/install, /sys/install）
 * 3. 自动注入 Swagger 路由（/sys/openapi, /sys/openapi/doc）
 *
 * 设计来源：authorization-refactoring-plan.md §2.11
 */
abstract class BaseMiddleware implements MiddlewareInterface
{
    /**
     * 排除路由列表（已解析 @ 引用 + 自动注入）
     * @var array
     */
    protected array $excludeRoutes = [];

    /**
     * 解析 exclude_routes 配置
     *
     * 支持两种配置格式：
     * 1. 数组格式：['/route1', '/route2']
     * 2. @引用格式：'@no_permission_routes' 或 ['@no_permission_routes', '/route3']
     *
     * @param array $config 中间件配置数组
     * @param string $sectionName 配置节名称（如 'auth', 'permission'），用于日志
     * @return array 解析后的路由数组
     */
    protected function resolveExcludeRoutes(array $config, string $sectionName = ''): array
    {
        $raw = $config['exclude_routes'] ?? [];

        // Step 1: 展开 @ 引用语法
        $routes = $this->expandReferences($raw);

        // Step 2: 自动注入平台级白名单（防止运营误删）
        $routes = array_values(array_unique(array_merge(
            $routes,
            SwaggerRoutes::excludeRoutes(),
            InstallGuard::platformRoutes()
        )));

        return $routes;
    }

    /**
     * 展开 @ 引用语法
     *
     * 支持：
     * - '@no_permission_routes' 字符串
     * - ['@no_permission_routes', '/sys/foo'] 混合数组
     *
     * @param array|string $raw 原始配置
     * @return array 展开后的路由数组
     */
    private function expandReferences(array|string $raw): array
    {
        $result = [];

        // 统一转为数组处理
        $items = is_string($raw) ? [$raw] : (array) $raw;

        foreach ($items as $item) {
            if (is_string($item) && str_starts_with($item, '@')) {
                // @ 引用：从配置中读取共享池
                $refName = substr($item, 1);
                $pool = config("plugin.nanoadmin.nanoadmin.{$refName}", []);
                if (is_array($pool)) {
                    $result = array_merge($result, $pool);
                }
            } else {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * 检查请求路径是否匹配排除路由（前缀匹配）
     *
     * @param Request $request
     * @return bool
     */
    protected function matchesExcludeRoute(Request $request): bool
    {
        $path = '/' . ltrim($request->path(), '/');

        foreach ($this->excludeRoutes as $route) {
            // 前缀匹配：'/sys/auth/login' 匹配 '/sys/auth/login/sub'
            if (str_starts_with($path, $route) || $path === $route) {
                return true;
            }
        }

        return false;
    }

    /**
     * 设置排除路由（用于测试或动态调整）
     *
     * @param array $routes
     */
    public function setExcludeRoutes(array $routes): void
    {
        $this->excludeRoutes = $routes;
    }

    /**
     * 获取排除路由列表
     *
     * @return array
     */
    public function getExcludeRoutes(): array
    {
        return $this->excludeRoutes;
    }

    /**
     * 添加排除路由
     *
     * @param string|array $routes
     */
    public function addExcludeRoutes(string|array $routes): void
    {
        if (is_string($routes)) {
            $routes = [$routes];
        }
        $this->excludeRoutes = array_unique(array_merge($this->excludeRoutes, $routes));
    }
}
