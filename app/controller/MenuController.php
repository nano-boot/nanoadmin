<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\attribute\AllowAnonymous;
use plugin\nanoadmin\app\attribute\Permission;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\menu\MenuQuery;
use plugin\nanoadmin\app\schema\menu\MenuRequest;
use plugin\nanoadmin\app\schema\menu\MenuResponse;
use plugin\nanoadmin\app\schema\menu\MenuSortRequest;
use plugin\nanoadmin\app\service\MenuService;
use plugin\nanoadmin\app\validator\menu\MenuValidator;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 菜单管理控制器
 *
 * 路由通过 OA 注解自动注册：
 *  GET    /sys/menu         tree   菜单树（支持搜索）
 *  POST   /sys/menu         store  创建菜单
 *  GET    /sys/menu/route   route  当前管理员可访问的路由
 *  GET    /sys/menu/{id}    show   菜单详情
 *  PUT    /sys/menu/{id}    update 更新菜单
 *  DELETE /sys/menu/{id}    destroy 删除菜单
 *  POST   /sys/menu/sort    sort   批量更新菜单排序
 *
 * Phase 2 注解化（来源：authorization-refactoring-plan.md §2.4）：
 *  - 类级 #[Permission] 提供兜底权限码 sys:menu（方法级未声明时用）
 *  - 方法级 #[Permission] 精确声明每个方法的权限码（与 config/nanoadmin.php route_permissions 对齐）
 *  - route() 方法用 #[AllowAnonymous(requirePermission: false)] 声明免权限
 *    （前端路由接口，已登录但不能要求权限，否则新用户登录后无法获取自己的菜单）
 */
#[OA\Tag(name: '菜单管理', description: '系统菜单管理（树形结构 + 排序）')]
#[Permission(title: '菜单管理', code: 'sys:menu', module: 'system')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class MenuController extends BaseController
{
    private MenuService $service;
    private MenuValidator $validator;

    public function __construct(MenuService $service, MenuValidator $validator)
    {
        $this->service = $service;
        $this->validator = $validator;
    }

    /**
     * 获取菜单树（支持搜索条件）
     */
    #[OA\Get(
        path: '/sys/menu',
        summary: '菜单树',
        description: '获取菜单树形结构，支持按关键词、状态、类型等条件搜索',
        tags: ['菜单管理'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => MenuQuery::class]
    )]
    #[Permission(title: '菜单列表', code: 'sys:menu:page', module: 'system', action: 'page')]
    #[DataResponse(example: [
        ['id' => 1, 'parent_id' => 0, 'name' => '系统管理', 'type' => 'D', 'children' => []],
    ])]
    public function tree(Request $request): Response
    {
        $params = $this->validator->scene('tree')->setGet()->check();
        return R::data($this->service->getMenuTreeWithSearch($params), '获取菜单树成功');
    }

    /**
     * 创建菜单
     */
    #[OA\Post(
        path: '/sys/menu',
        summary: '创建菜单',
        tags: ['菜单管理'],
        x: [OpenApiModifier::X_REQUEST_BODY => MenuRequest::class]
    )]
    #[Permission(title: '创建菜单', code: 'sys:menu:create', module: 'system', action: 'create')]
    #[DataResponse(schema: MenuResponse::class)]
    public function store(Request $request): Response
    {
        $data = $this->validator->scene('store')->setPost()->check();
        return R::created($this->service->createMenu($data), '创建菜单成功');
    }

    /**
     * 获取当前管理员可访问的路由配置
     *
     * 已登录但免权限：前端路由登录后立即调用，
     * 否则新用户登录后会因权限不足拿不到自己的菜单 → 死循环。
     */
    #[OA\Get(
        path: '/sys/menu/route',
        summary: '当前管理员路由',
        description: '获取当前登录管理员可访问的前端路由配置（含按钮权限）',
        tags: ['菜单管理']
    )]
    #[AllowAnonymous(requireToken: true, requirePermission: false, description: '当前管理员路由（已登录免权限）')]
    #[DataResponse(example: [
        ['id' => 1, 'name' => '系统管理', 'path' => '/system', 'component' => 'Layout', 'children' => []],
    ])]
    public function route(Request $request): Response
    {
        $adminId = (int)($request->adminId ?? 0);
        return R::success($this->service->getAdminRoutes($adminId), '获取路由配置成功');
    }

    /**
     * 获取菜单详情
     */
    #[OA\Get(
        path: '/sys/menu/{id}',
        summary: '菜单详情',
        tags: ['菜单管理'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '菜单ID'],
        ]]
    )]
    #[Permission(title: '菜单详情', code: 'sys:menu:view', module: 'system', action: 'page')]
    #[DataResponse(schema: MenuResponse::class)]
    public function show(int $id): Response
    {
        $params = $this->validator->scene('show')->setPath()->check();
        return R::success($this->service->getMenuFormData($params['id']), '获取菜单详情成功');
    }

    /**
     * 更新菜单
     */
    #[OA\Put(
        path: '/sys/menu/{id}',
        summary: '更新菜单',
        tags: ['菜单管理'],
        x: [
            OpenApiModifier::X_PATH_PARAMETERS => [
                'id' => ['type' => 'integer', 'description' => '菜单ID'],
            ],
            OpenApiModifier::X_REQUEST_BODY => MenuRequest::class,
        ]
    )]
    #[Permission(title: '更新菜单', code: 'sys:menu:update', module: 'system', action: 'update')]
    #[DataResponse(schema: MenuResponse::class)]
    public function update(Request $request, int $id): Response
    {
        $params = $this->validator->scene('update')->setAll()->check();
        return R::data($this->service->updateMenu($params), '更新菜单成功');
    }

    /**
     * 删除菜单
     */
    #[OA\Delete(
        path: '/sys/menu/{id}',
        summary: '删除菜单',
        tags: ['菜单管理'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '菜单ID'],
        ]]
    )]
    #[Permission(title: '删除菜单', code: 'sys:menu:delete', module: 'system', action: 'delete')]
    #[DataResponse()]
    public function destroy(int $id): Response
    {
        $params = $this->validator->scene('destroy')->setPath()->check();
        $this->service->deleteMenu($params['id']);
        return R::deleted('删除菜单成功');
    }

    /**
     * 批量更新菜单排序
     */
    #[OA\Post(
        path: '/sys/menu/sort',
        summary: '批量更新菜单排序',
        tags: ['菜单管理'],
        x: [OpenApiModifier::X_REQUEST_BODY => MenuSortRequest::class]
    )]
    #[Permission(title: '菜单排序', code: 'sys:menu:update', module: 'system', action: 'update')]
    #[DataResponse()]
    public function sort(Request $request): Response
    {
        $data = $this->validator->scene('sort')->setPost()->check();
        $this->service->batchUpdateSort($data['sort_data']);
        return R::success(null, '菜单排序成功');
    }
}
