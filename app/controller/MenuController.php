<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
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
 */
#[OA\Tag(name: '菜单管理', description: '系统菜单管理（树形结构 + 排序）')]
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
    #[DataResponse(schema: MenuResponse::class)]
    public function store(Request $request): Response
    {
        $data = $this->validator->scene('store')->setPost()->check();
        return R::created($this->service->createMenu($data), '创建菜单成功');
    }

    /**
     * 获取当前管理员可访问的路由配置
     */
    #[OA\Get(
        path: '/sys/menu/route',
        summary: '当前管理员路由',
        description: '获取当前登录管理员可访问的前端路由配置（含按钮权限）',
        tags: ['菜单管理']
    )]
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
    #[DataResponse()]
    public function sort(Request $request): Response
    {
        $data = $this->validator->scene('sort')->setPost()->check();
        $this->service->batchUpdateSort($data['sort_data']);
        return R::success(null, '菜单排序成功');
    }
}
