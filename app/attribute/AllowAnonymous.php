<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\attribute;

/**
 * 匿名访问声明注解（Phase 2 新增）
 *
 * 用于声明某个 Controller 方法（也支持类级）允许"免登录"或"免权限"访问。
 *
 * 设计来源：authorization-refactoring-plan.md §2.2.2
 *   - 借鉴 madong-admin/vendor/madong/swagger/src/attribute/AllowAnonymous.php
 *   - 改进点：双参数 requireToken / requirePermission，对齐 madong 的语义
 *
 * 与 BaseController 的 $noNeedLogin / $noNeedPermission 属性关系：
 *   - #[AllowAnonymous] 是类型安全的强声明，IDE 可提示
 *   - $noNeedLogin / $noNeedPermission 属性是字符串方法名列表（saiadmin 风格，向后兼容）
 *   - 两者共存，注解优先级更高（详见 ReflectionCache::getAllowAnonymous 实现）
 *
 * 四种典型场景：
 *
 *   1) 完全匿名（登录、刷新 token、验证码）
 *      #[AllowAnonymous(requireToken: false, requirePermission: false)]
 *
 *   2) 已登录但免权限（个人信息、我的菜单、动态路由）
 *      #[AllowAnonymous(requireToken: true, requirePermission: false)]
 *
 *   3) 已登录且需要权限（普通接口，不写此注解）
 *
 *   4) 退出登录（requireToken: false，因为要清掉 token 才能调用）
 *      #[AllowAnonymous(requireToken: false, requirePermission: false)]
 *
 * 平台路由（/install, /sys/install, /sys/openapi）不需要写此注解，
 * 由 BaseMiddleware::resolveExcludeRoutes() 自动注入到 exclude_routes。
 *
 * 使用示例：
 *
 *   class AuthController {
 *       #[AllowAnonymous(requireToken: false, requirePermission: false, description: '登录')]
 *       public function login(Request $request): Response { ... }
 *
 *       #[AllowAnonymous(requireToken: true, requirePermission: false, description: '个人中心')]
 *       public function info(Request $request): Response { ... }
 *   }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AllowAnonymous
{
    /**
     * @param bool     $requireToken       是否要求 token 校验（false=免登录）
     * @param bool     $requirePermission  是否要求权限校验（false=免权限）
     * @param string|null $description     描述（给开发者看）
     */
    public function __construct(
        public bool $requireToken = true,
        public bool $requirePermission = true,
        public ?string $description = null,
    ) {
    }
}