<?php

namespace plugin\nanoadmin\app\middleware;

use Webman\Http\Response;
use Webman\Http\Request;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\PermissionInferrer;
use plugin\nanoadmin\app\common\cache\AdminAuthCache;
use plugin\nanoadmin\app\library\annotation\ReflectionCache;

/**
 * 权限验证中间件
 *
 * exclude_routes 由 BaseMiddleware::resolveExcludeRoutes() 统一解析：
 * - 支持 @no_permission_routes 引用语法
 * - 自动注入平台路由 + Swagger 路由
 *
 * Fail-closed: 未登记权限的路由直接拒绝访问
 *
 * Phase 2 P0 完成（来源：authorization-refactoring-plan.md §2.9）：
 * 权限码查找优先级：
 *  1. 方法级 #[Permission] 注解（最精确）
 *  2. 类级 #[Permission] 注解（兜底）
 *  3. PermissionInferrer 自动推断（替代原 route_permissions 配置）
 *  4. 都无 → 返回 null（fail-closed 走 403）
 *
 * 权限校验使用 AdminAuthCache（替代直接调 $admin->hasPermission）：
 *  - MD5 自动失效（权限/角色表变更时缓存自动失效）
 *  - 减少每次请求都做的 admin→roles→permissions 三表关联
 *
 * 放行检测（shouldSkipPermissionCheck）4 层：
 *  1. 路由前缀白名单（permission.exclude_routes + 平台级自动注入）
 *  2. #[AllowAnonymous(requirePermission: false)] 注解
 *  3. $noNeedPermission 属性（见 ReflectionCache::getNoNeedPermission）
 *  4. 不跳过，进入权限校验
 */
class PermissionMiddleware extends BaseMiddleware
{
    /**
     * 超级管理员角色代码（从 config 读取）
     * @var array
     */
    protected array $superAdminRoles = [];

    /**
     * AdminAuthCache 实例
     * @var AdminAuthCache|null
     */
    protected ?AdminAuthCache $authCache = null;

    /**
     * 解析后的配置缓存
     * @var array|null
     */
    protected static ?array $cachedConfig = null;

    public function __construct()
    {
        $this->loadConfig();
        $this->authCache = new AdminAuthCache();
    }

    /**
     * 获取 AdminAuthCache（懒加载）
     */
    protected function getAuthCache(): AdminAuthCache
    {
        if ($this->authCache === null) {
            $this->authCache = new AdminAuthCache();
        }
        return $this->authCache;
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

        $this->superAdminRoles  = self::$cachedConfig['super_admin_roles'] ?? [];
        // 使用 BaseMiddleware 的 resolveExcludeRoutes 解析路由（含 @ 引用 + 自动注入）
        $this->excludeRoutes    = $this->resolveExcludeRoutes(self::$cachedConfig);
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

            // Fail-closed: 未登记权限的路由直接拒绝访问
            if (empty($requiredPermission)) {
                throw new ApiException(Code::FORBIDDEN, '该接口未登记权限，请联系管理员');
            }

            // 检查用户是否有权限（使用 AdminAuthCache）
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
     * 检查是否应该跳过权限验证（4 层优先级）
     *
     * 优先级（命中即返回）：
     *  1. 路由前缀白名单（permission.exclude_routes + 平台级自动注入）
     *  2. #[AllowAnonymous(requirePermission: false)] 注解
     *  3. $noNeedPermission 属性
     *  4. 不跳过，进入权限校验
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldSkipPermissionCheck(Request $request): bool
    {
        // Layer 1：路由前缀白名单（平台级自动注入 + 业务配置）
        if ($this->matchesExcludeRoute($request)) {
            return true;
        }

        $controller = $request->controller ?? '';
        $action     = $request->action ?? '';

        if ($controller !== '' && $action !== ''
            && class_exists($controller) && method_exists($controller, $action)) {

            // Layer 2：#[AllowAnonymous] 注解（强类型优先）
            $anon = ReflectionCache::getAllowAnonymous($controller, $action);
            if ($anon !== null && !$anon['requirePermission']) {
                return true; // 注解声明放行权限
            }

            // Layer 3：$noNeedPermission 属性
            $noNeed = ReflectionCache::getNoNeedPermission($controller);
            if (in_array($action, $noNeed, true)) {
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
     * 获取当前路由需要的权限码（Phase 2 注解 + 推断双来源）
     *
     * 优先级：
     *  1. 方法级 #[Permission] 注解（最精确，Phase 3 支持多权限 OR 语义）
     *  2. 类级 #[Permission] 注解（兜底）
     *  3. PermissionInferrer 自动推断（替代原 route_permissions 配置）
     *  4. 都无 → 返回 null（fail-closed 走 403）
     *
     * @param Request $request
     * @return string|null
     */
    protected function getRequiredPermission(Request $request): ?string
    {
        $controller = $request->controller ?? '';
        $action     = $request->action ?? '';

        if ($controller !== '' && $action !== ''
            && class_exists($controller) && method_exists($controller, $action)) {

            // 1. 方法级 #[Permission] 注解
            $methodAttrs = ReflectionCache::getPermissionAttributes($controller, $action);
            if (!empty($methodAttrs)) {
                return $methodAttrs[0]['code']; // 多权限码取第一个（Phase 3 支持 OR 语义）
            }

            // 2. 类级 #[Permission] 注解（兜底）
            $classAttrs = ReflectionCache::getClassAttributes($controller);
            if (!empty($classAttrs)) {
                return $classAttrs[0]['code'];
            }
        }

        // 3. PermissionInferrer 自动推断（替代原 route_permissions 配置）
        $inferred = PermissionInferrer::infer($request->method(), $request->path());
        if ($inferred !== null) {
            return $inferred;
        }

        // 4. 没有匹配（fail-closed）
        return null;
    }

    /**
     * 检查用户是否有指定权限（通过 AdminAuthCache）
     *
     * @param mixed $admin
     * @param string $permission
     * @return bool
     */
    protected function hasPermission($admin, string $permission): bool
    {
        if (!$admin || !isset($admin->id)) {
            return false;
        }

        $userPermissions = $this->getAuthCache()->getAdminPermissions((int) $admin->id);
        return in_array($permission, $userPermissions, true);
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