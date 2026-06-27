<?php

namespace plugin\nanoadmin\app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\library\swagger\SwaggerRoutes;

/**
 * 权限验证中间件
 * 基于路由的权限检查
 */
class PermissionMiddleware implements MiddlewareInterface
{
    /**
     * 路由权限映射（从 config('plugin.nanoadmin.nanoadmin.permission.route_permissions') 读取）
     * 格式：['METHOD:/path' => '权限代码']
     * @var array
     */
    protected array $routePermissions = [];

    /**
     * 不需要权限验证的路由（从 config 读取）
     * @var array
     */
    protected array $excludeRoutes = [];

    /**
     * 超级管理员角色代码（从 config 读取）
     * @var array
     */
    protected array $superAdminRoles = [];

    /**
     * 解析后的配置缓存
     * @var array|null
     */
    protected static ?array $cachedConfig = null;

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * 从配置文件加载默认配置
     */
    protected function loadConfig(): void
    {
        if (self::$cachedConfig === null) {
            $config = function_exists('config') ? config('plugin.nanoadmin.nanoadmin.permission', []) : [];
            self::$cachedConfig = is_array($config) ? $config : [];
        }

        $this->routePermissions = self::$cachedConfig['route_permissions'] ?? [];
        $this->excludeRoutes     = self::$cachedConfig['exclude_routes'] ?? [];
        // 自动注入 swagger / openapi 路由白名单（随 swagger.php ui_route / doc_route / enabled 同步）
        $this->excludeRoutes     = array_values(array_unique(array_merge($this->excludeRoutes, SwaggerRoutes::excludeRoutes())));
        $this->superAdminRoles   = self::$cachedConfig['super_admin_roles'] ?? [];
    }

    /**
     * 处理请求
     * @param Request $request
     * @param callable $handler
     * @return Response
     */
    public function process(Request $request, callable $handler): Response
    {
        try {
            // 检查是否需要权限验证
            if ($this->shouldSkipPermissionCheck($request)) {
                return $handler($request);
            }

            // 检查用户是否已认证
            if (!isset($request->admin)) {
                throw new ApiException(Code::UNAUTHORIZED, '用户未认证');
            }

            $admin = $request->admin;

            // 检查是否为超级管理员
            if ($this->isSuperAdmin($admin)) {
                return $handler($request);
            }

            // 获取当前路由需要的权限
            $requiredPermission = $this->getRequiredPermission($request);
            
            if (empty($requiredPermission)) {
                // 如果没有配置权限要求，默认允许访问
                return $handler($request);
            }

            // 检查用户是否有权限
            if (!$this->hasPermission($admin, $requiredPermission)) {
                throw new ApiException(Code::FORBIDDEN, '权限不足，无法访问该资源');
            }

            // 权限验证通过，继续处理请求
            return $handler($request);

        } catch (ApiException $e) {
            return $this->forbiddenResponse($e->getMessage(), $e->getErrorCode());
        } catch (\Exception $e) {
            return $this->forbiddenResponse('权限验证失败', Code::FORBIDDEN->value);
        }
    }

    /**
     * 检查是否应该跳过权限验证
     * @param Request $request
     * @return bool
     */
    protected function shouldSkipPermissionCheck(Request $request): bool
    {
        $path = $request->path();
        
        foreach ($this->excludeRoutes as $route) {
            if (str_starts_with($path, $route)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 检查是否为超级管理员
     * @param mixed $admin
     * @return bool
     */
    protected function isSuperAdmin($admin): bool
    {
        if (!$admin || !method_exists($admin, 'hasRole')) {
            return false;
        }

        foreach ($this->superAdminRoles as $roleCode) {
            if ($admin->hasRole($roleCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取当前路由需要的权限
     * @param Request $request
     * @return string|null
     */
    protected function getRequiredPermission(Request $request): ?string
    {
        $method = strtoupper($request->method());
        $path = $request->path();

        // 构建路由键
        $routeKey = $method . ':' . $path;

        // 直接匹配
        if (isset($this->routePermissions[$routeKey])) {
            return $this->routePermissions[$routeKey];
        }

        // 模式匹配（支持通配符）
        foreach ($this->routePermissions as $pattern => $permission) {
            if ($this->matchRoute($pattern, $routeKey)) {
                return $permission;
            }
        }

        return null;
    }

    /**
     * 路由模式匹配
     * @param string $pattern 路由模式
     * @param string $route 实际路由
     * @return bool
     */
    protected function matchRoute(string $pattern, string $route): bool
    {
        // 将通配符转换为正则表达式
        $regex = str_replace(['*', '/'], ['[^/]+', '\/'], $pattern);
        $regex = '/^' . $regex . '$/';

        return preg_match($regex, $route) === 1;
    }

    /**
     * 检查用户是否有指定权限
     * @param mixed $admin
     * @param string $permission
     * @return bool
     */
    protected function hasPermission($admin, string $permission): bool
    {
        if (!$admin || !method_exists($admin, 'hasPermission')) {
            return false;
        }

        return $admin->hasPermission($permission);
    }

    /**
     * 返回权限不足响应
     * @param string $message
     * @param int $code
     * @return Response
     */
    protected function forbiddenResponse(string $message, int $code): Response
    {
        return R::forbidden($message);
    }

    /**
     * 添加路由权限映射
     * @param string $route 路由模式
     * @param string $permission 权限代码
     */
    public function addRoutePermission(string $route, string $permission): void
    {
        $this->routePermissions[$route] = $permission;
    }

    /**
     * 批量添加路由权限映射
     * @param array $permissions 权限映射数组
     */
    public function addRoutePermissions(array $permissions): void
    {
        $this->routePermissions = array_merge($this->routePermissions, $permissions);
    }

    /**
     * 设置路由权限映射
     * @param array $permissions 权限映射数组
     */
    public function setRoutePermissions(array $permissions): void
    {
        $this->routePermissions = $permissions;
    }

    /**
     * 获取路由权限映射
     * @return array
     */
    public function getRoutePermissions(): array
    {
        return $this->routePermissions;
    }

    /**
     * 添加排除路由
     * @param string|array $routes
     */
    public function addExcludeRoutes(string|array $routes): void
    {
        if (is_string($routes)) {
            $routes = [$routes];
        }
        
        $this->excludeRoutes = array_merge($this->excludeRoutes, $routes);
    }

    /**
     * 设置排除路由
     * @param array $routes
     */
    public function setExcludeRoutes(array $routes): void
    {
        $this->excludeRoutes = $routes;
    }

    /**
     * 获取排除路由列表
     * @return array
     */
    public function getExcludeRoutes(): array
    {
        return $this->excludeRoutes;
    }

    /**
     * 设置超级管理员角色
     * @param array $roles
     */
    public function setSuperAdminRoles(array $roles): void
    {
        $this->superAdminRoles = $roles;
    }

    /**
     * 获取超级管理员角色列表
     * @return array
     */
    public function getSuperAdminRoles(): array
    {
        return $this->superAdminRoles;
    }
}