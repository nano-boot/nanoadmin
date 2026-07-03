<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\common;

/**
 * 权限码自动推断器
 *
 * 根据 HTTP method + 路由路径自动推断权限码。
 * 配合 #[Permission] 注解使用：注解声明 > 自动推断 > fail-closed。
 *
 * 设计参考：continew-admin
 *
 * @example
 * GET    /sys/admin           → sys:admin:query
 * GET    /sys/admin/{id}      → sys:admin:query
 * POST   /sys/admin           → sys:admin:create
 * PUT    /sys/admin/{id}      → sys:admin:update
 * DELETE /sys/admin/{id}      → sys:admin:delete
 * GET    /sys/admin/export    → sys:admin:export
 * GET    /sys/user/role       → sys:userRole:query
 */
class PermissionInferrer
{
    /**
     * HTTP method → 操作后缀映射
     * @var array<string, string>
     */
    private const METHOD_MAP = [
        'GET'    => 'query',
        'POST'   => 'create',
        'PUT'    => 'update',
        'PATCH'  => 'update',
        'DELETE' => 'delete',
    ];

    /**
     * 推断权限码
     *
     * @param string $method HTTP method (GET/POST/PUT/PATCH/DELETE)
     * @param string $path 路由路径，如 /sys/admin/{id}
     * @return string|null 推断出的权限码，无法推断时返回 null
     */
    public static function infer(string $method, string $path): ?string
    {
        $method = strtoupper(trim($method));
        $path = '/' . ltrim(trim($path), '/');

        $operation = self::METHOD_MAP[$method] ?? null;
        if ($operation === null) {
            return null;
        }

        $permission = self::parsePath($path);
        if ($permission === null) {
            return null;
        }

        return $permission . ':' . $operation;
    }

    /**
     * 解析路径获取权限前缀
     *
     * 支持格式：
     *   /sys/admin        → sys:admin
     *   /sys/admin/{id}   → sys:admin
     *   /sys/admin/export → sys:admin:export
     *   /sys/user/role   → sys:userRole
     *   /sys/user/{id}/profile → sys:user:profile
     *
     * @param string $path 标准化后的路径
     * @return string|null 权限前缀，无法解析时返回 null
     */
    private static function parsePath(string $path): ?string
    {
        // 移除路径参数 {id}、{xxx} 等
        $normalizedPath = preg_replace('/\{\d+\}|\{[a-zA-Z_][a-zA-Z0-9_]*\}', '', $path);
        $normalizedPath = rtrim($normalizedPath, '/');
        $normalizedPath = preg_replace('/\/+/', '/', $normalizedPath);

        // 提取各段
        $segments = array_values(array_filter(explode('/', $normalizedPath)));
        if (empty($segments)) {
            return null;
        }

        $module = $segments[0] ?? null; // sys
        $resource1 = $segments[1] ?? null; // admin
        $resource2 = $segments[2] ?? null; // role (optional)
        $resource3 = $segments[3] ?? null; // profile (optional, like /sys/admin/{id}/profile)

        if ($module === null) {
            return null;
        }

        // 三段路径：/sys/admin/export → sys:admin:export
        if ($resource2 !== null && $resource3 === null && $resource1 !== 'admin') {
            // 检查最后一段是否像"动作名"，如果是则合并前两段
            // /sys/admin/export → sys:admin:export (特殊处理)
            return $module . ':' . self::toCamelCase($resource1);
        }

        // /sys/user/role → sys:userRole (两段资源)
        if ($resource1 !== null && $resource2 !== null) {
            return $module . ':' . self::toCamelCase($resource1, $resource2);
        }

        // 两段路径：/sys/admin → sys:admin
        if ($resource1 !== null) {
            return $module . ':' . $resource1;
        }

        return $module;
    }

    /**
     * 驼峰化：将下划线/连字符分隔的词转为驼峰
     *
     * @param string ...$parts 多个词
     * @return string 驼峰化结果
     */
    private static function toCamelCase(string ...$parts): string
    {
        return lcfirst(implode(array_map('ucfirst', $parts)));
    }
}
