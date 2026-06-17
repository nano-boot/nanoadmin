<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\role\RoleQuery;
use plugin\nanoadmin\app\schema\role\RoleRequest;
use plugin\nanoadmin\app\schema\role\RoleResponse;
use plugin\nanoadmin\app\schema\role\RolePermissionResponse;
use plugin\nanoadmin\app\schema\role\RoleMenuResponse;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\validator\RoleValidator;
use plugin\nanoadmin\app\service\RoleService;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 角色控制器
 */
#[OA\Tag(name: '角色', description: '角色管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class RoleController extends AbstractResourceController
{
    private RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        new RoleValidator(true);
        $this->roleService = $roleService;
    }

    protected function getService(): RoleService
    {
        return $this->roleService;
    }

    protected function getModelName(): string
    {
        return 'Role';
    }

    #[OA\Get(
        path: '/sys/role',
        summary: '角色列表',
        tags: ['角色'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => RoleQuery::class]
    )]
    #[PageResponse(schema: RoleResponse::class)]
    public function page(Request $request): Response
    {
        return parent::page($request);
    }

    #[OA\Get(
        path: '/sys/role/select',
        summary: '角色下拉列表',
        tags: ['角色']
    )]
    #[DataResponse(schema: RoleResponse::class)]
    public function selectList(): Response
    {
        return R::data($this->roleService->getEnabledRoles());
    }

    #[OA\Get(
        path: '/sys/role/{id}',
        summary: '角色详情',
        tags: ['角色']
    )]
    #[DataResponse(schema: RoleResponse::class)]
    public function show(int $id = 0): Response
    {
        return parent::show($id);
    }

    #[OA\Post(
        path: '/sys/role',
        summary: '创建角色',
        tags: ['角色'],
        x: [OpenApiModifier::X_REQUEST_BODY => RoleRequest::class]
    )]
    #[DataResponse()]
    public function create(Request $request): Response
    {
        return parent::create($request);
    }

    #[OA\Put(
        path: '/sys/role/{id}',
        summary: '更新角色',
        tags: ['角色'],
        x: [OpenApiModifier::X_REQUEST_BODY => RoleRequest::class]
    )]
    #[DataResponse()]
    public function update(Request $request, int $id): Response
    {
        $this->roleService->updateRole($id, $request->post());
        return R::ok();
    }

    #[OA\Delete(
        path: '/sys/role/{id}',
        summary: '删除角色',
        tags: ['角色']
    )]
    #[DataResponse()]
    public function destroy(int $id): Response
    {
        return parent::destroy($id);
    }

    #[OA\Delete(
        path: '/sys/role/batch',
        summary: '批量删除角色',
        tags: ['角色']
    )]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        return parent::batchDestroy($request);
    }

    #[OA\Post(
        path: '/sys/role/{id}/permissions',
        summary: '分配权限',
        description: '为角色分配菜单ID和权限标识',
        tags: ['角色'],
        x: [OpenApiModifier::X_REQUEST_BODY => RolePermissionResponse::class]
    )]
    #[DataResponse()]
    public function assignPermissions(int $id, Request $request): Response
    {
        $id = (int)$id;
        if ($id <= 0) {
            return R::error('角色ID无效', Code::PARAMETER_ERROR->value);
        }

        $menuIds = $request->post('menuIds', []);
        $authCodes = $request->post('authCodes', []);

        if (!is_array($menuIds)) {
            return R::error('菜单ID列表格式错误', Code::PARAMETER_ERROR->value);
        }

        if (!is_array($authCodes)) {
            return R::error('权限编码列表格式错误', Code::PARAMETER_ERROR->value);
        }

        $menuIds = array_map('intval', $menuIds);
        $menuIds = array_filter($menuIds, fn($id) => $id > 0);

        $authCodes = array_filter($authCodes, fn($code) => is_string($code) && !empty($code));

        $result = $this->roleService->assignPermissions($id, [
            'menuIds' => array_values($menuIds),
            'authCodes' => array_values($authCodes)
        ]);

        return R::data($result, '分配权限成功');
    }

    #[OA\Get(
        path: '/sys/role/{id}/permissions',
        summary: '获取角色权限',
        tags: ['角色']
    )]
    #[DataResponse(schema: RolePermissionResponse::class)]
    public function getPermissions(int $id): Response
    {
        $id = (int)$id;
        if ($id <= 0) {
            return R::error('角色ID无效', Code::PARAMETER_ERROR->value);
        }
        $permissions = $this->roleService->getRolePermissions($id);
        return R::data($permissions, '获取角色权限成功');
    }

    #[OA\Post(
        path: '/sys/role/{id}/menus',
        summary: '分配菜单',
        description: '为角色分配菜单',
        tags: ['角色']
    )]
    #[DataResponse()]
    public function assignMenus(int $id, Request $request): Response
    {
        $id = (int)$id;
        if ($id <= 0) {
            return R::error('角色ID无效', Code::PARAMETER_ERROR->value);
        }

        $menuIds = $request->post('menuIds', []);
        if (!is_array($menuIds)) {
            return R::error('菜单ID列表格式错误', Code::PARAMETER_ERROR->value);
        }

        $menuIds = array_map('intval', $menuIds);
        $menuIds = array_filter($menuIds, fn($id) => $id > 0);

        $result = $this->roleService->assignMenus($id, $menuIds);
        return R::data($result, '分配菜单成功');
    }

    #[OA\Get(
        path: '/sys/role/{id}/menus',
        summary: '获取角色菜单',
        tags: ['角色']
    )]
    #[DataResponse(schema: RoleMenuResponse::class)]
    public function getMenus(int $id): Response
    {
        $id = (int)$id;
        if ($id <= 0) {
            return R::error('角色ID无效', Code::PARAMETER_ERROR->value);
        }

        $menus = $this->roleService->getRoleMenus($id);
        return R::list($menus, '获取角色菜单成功');
    }
}
