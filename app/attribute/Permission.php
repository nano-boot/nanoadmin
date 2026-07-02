<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\attribute;

/**
 * 权限声明注解（Phase 2 新增）
 *
 * 标注在 Controller 类或方法上，声明该接口需要的权限。
 * PermissionMiddleware 优先读取此注解，未匹配时再回退到 route_permissions 配置。
 *
 * 设计来源：authorization-refactoring-plan.md §2.2.1
 *
 * 字段说明：
 *  - title    必填：权限中文名（写入 th_sys_menu.title）
 *  - code     必填：权限码（运行时校验唯一标识）
 *  - module   选填：所属模块（菜单分组、Phase 3 扫描命令分类轴）
 *  - action   选填：page/create/update/delete/export
 *  - log      选填：是否记录操作日志（默认 true）
 *
 * 使用示例：
 *
 *   // 方法级注解（最常用）
 *   #[Permission(title: '管理员列表', code: 'sys:admin:page', module: 'system')]
 *   public function index(Request $request): Response { ... }
 *
 *   // 类级注解（兜底，方法未声明时使用）
 *   #[Permission(title: '管理员管理', code: 'sys:admin', module: 'system')]
 *   class AdminController { ... }
 *
 *   // 多权限码（任一即可访问，Phase 3 支持 OR 语义）
 *   #[Permission(title: '导出', code: 'sys:admin:export', module: 'system')]
 *   #[Permission(title: '列表', code: 'sys:admin:page', module: 'system')]
 *   public function export(Request $request): Response { ... }
 *
 * 关系：
 *  - 与 #[OA\Get/Post/Put/Delete/Patch] 正交，不重复声明 path
 *  - PermissionMiddleware 读取优先级：方法级 > 类级 > route_permissions 配置
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Permission
{
    /**
     * @param string $title  权限中文名（写入 th_sys_menu.title）
     * @param string $code   权限码（运行时校验唯一标识）
     * @param string $module 所属模块（菜单分组用）
     * @param string $action 操作类型（page/create/update/delete/export）
     * @param bool   $log    是否记录操作日志（默认 true）
     */
    public function __construct(
        public string $title,
        public string $code,
        public string $module = '',
        public string $action = '',
        public bool $log = true,
    ) {
    }
}