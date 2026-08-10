<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\attribute\Permission;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\role\RoleQuery;
use plugin\nanoadmin\app\schema\role\RoleRequest;
use plugin\nanoadmin\app\schema\role\RoleResponse;
use plugin\nanoadmin\app\schema\role\RolePermissionResponse;
use plugin\nanoadmin\app\schema\role\RoleMenuResponse;
use plugin\nanoadmin\app\schema\role\RoleDeptRequest;
use plugin\nanoadmin\app\schema\role\RoleDeptResponse;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\validator\role\RoleValidator;
use plugin\nanoadmin\app\service\RoleService;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 角色控制器
 */
#[OA\Tag(name: '角色', description: '角色管理')]
#[Permission(title: '角色管理', code: 'sys:role')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class RoleController extends BaseController
{
    private RoleService $roleService;
    private RoleValidator $validator;

    public function __construct(RoleService $roleService, RoleValidator $validator)
    {
        $this->roleService = $roleService;
        $this->validator = $validator;
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
        $params = $this->validator->scene('page')->setGet()->check();
        return R::paginate($this->roleService->getPage($params));
    }

    #[OA\Get(
        path: '/sys/role/select',
        summary: '角色下拉列表',
        tags: ['角色']
    )]
    #[Permission(title: '角色下拉列表', code: 'sys:role:select-list', action: 'query')]
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
    #[Permission(title: '角色详情', code: 'sys:role:show', action: 'query')]
    #[DataResponse(schema: RoleResponse::class)]
    public function show(int $id): Response
    {
        $this->validator->scene('show')->setPath()->check();
        $data = $this->roleService->getById($id);
        return R::success($data, '获取详情成功');
    }

    #[OA\Post(
        path: '/sys/role',
        summary: '创建角色',
        tags: ['角色'],
        x: [OpenApiModifier::X_REQUEST_BODY => RoleRequest::class]
    )]
    #[Permission(title: '创建角色', code: 'sys:role:create', action: 'create')]
    #[DataResponse()]
    public function create(Request $request): Response
    {
        $data = $this->validator->scene('store')->setPost()->check();
        $result = $this->roleService->createRole($data);
        return R::created($result);
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
        $data = $this->validator
            ->scene('update')
            ->setAll()
            ->check();
        $this->roleService->updateRole($id, $data);
        return R::ok();
    }

    #[OA\Delete(
        path: '/sys/role/{id}',
        summary: '删除角色',
        tags: ['角色']
    )]
    #[Permission(title: '删除角色', code: 'sys:role:delete', action: 'delete')]
    #[DataResponse()]
    public function delete(int $id): Response
    {
        $this->validator->scene('destroy')->setPath()->check();
        $this->roleService->deleteRole($id);
        return R::ok('删除成功');
    }

    #[OA\Delete(
        path: '/sys/role/batch',
        summary: '批量删除角色',
        tags: ['角色']
    )]
    #[Permission(title: '批量删除角色', code: 'sys:role:batch-delete', action: 'delete')]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        $this->validator->scene('batchDestroy')->setPost()->check();
        $ids = $request->post('ids', []);
        $result = $this->roleService->batchDeleteRoles($ids);
        return R::success($result, '批量删除成功');
    }

    #[OA\Post(
        path: '/sys/role/{id}/permissions',
        summary: '分配权限',
        description: '为角色分配菜单ID和权限标识',
        tags: ['角色'],
        x: [OpenApiModifier::X_REQUEST_BODY => RolePermissionResponse::class]
    )]
    #[Permission(title: '分配角色权限', code: 'sys:role:assign-permission', action: 'update')]
    #[DataResponse()]
    public function assignPermissions(int $id, Request $request): Response
    {
        $data = $this->validator
            ->scene('assignPermissions')
            ->check();

        $result = $this->roleService->assignPermissions($id, [
            'menuIds' => array_values(array_filter($data['menuIds'] ?? [], fn($v) => $v > 0)),
            'authCodes' => array_values(array_filter($data['authCodes'] ?? [], fn($v) => is_string($v) && $v !== '')),
        ]);

        return R::data($result, '分配权限成功');
    }

    #[OA\Get(
        path: '/sys/role/{id}/permissions',
        summary: '获取角色权限',
        tags: ['角色']
    )]
    #[Permission(title: '查看角色权限', code: 'sys:role:get-permissions', action: 'query')]
    #[DataResponse(schema: RolePermissionResponse::class)]
    public function getPermissions(int $id): Response
    {
        $this->validator->scene('show')->setPath()->check();
        $permissions = $this->roleService->getRolePermissions($id);
        return R::data($permissions, '获取角色权限成功');
    }

    #[OA\Post(
        path: '/sys/role/{id}/menus',
        summary: '分配菜单',
        description: '为角色分配菜单',
        tags: ['角色']
    )]
    #[Permission(title: '分配角色菜单', code: 'sys:role:assign-menu', action: 'update')]
    #[DataResponse()]
    public function assignMenus(int $id, Request $request): Response
    {
        $data = $this->validator
            ->scene('assignMenus')
            ->check();

        $menuIds = array_values(array_filter($data['menuIds'] ?? [], fn($v) => $v > 0));
        $result = $this->roleService->assignMenus($id, $menuIds);
        return R::data($result, '分配菜单成功');
    }

    #[OA\Get(
        path: '/sys/role/{id}/menus',
        summary: '获取角色菜单',
        tags: ['角色']
    )]
    #[Permission(title: '查看角色菜单', code: 'sys:role:get-menus', action: 'query')]
    #[DataResponse(schema: RoleMenuResponse::class)]
    public function getMenus(int $id): Response
    {
        $this->validator->scene('show')->setPath()->check();
        $menus = $this->roleService->getRoleMenus($id);
        return R::list($menus, '获取角色菜单成功');
    }

    #[OA\Post(
        path: '/sys/role/{id}/depts',
        summary: '分配数据权限部门',
        description: '为角色分配数据权限部门，控制角色下用户能查看哪些部门的数据',
        tags: ['角色'],
        x: [OpenApiModifier::X_REQUEST_BODY => RoleDeptRequest::class]
    )]
    #[Permission(title: '分配角色数据权限', code: 'sys:role:assign-dept', action: 'update')]
    #[DataResponse()]
    public function assignDepts(int $id, Request $request): Response
    {
        $data = $this->validator
            ->scene('assignDepts')
            ->check();

        $deptIds = array_values(array_filter($data['deptIds'] ?? [], fn($v) => $v > 0));
        $result = $this->roleService->assignDepts($id, $deptIds);
        return R::data($result, '分配数据权限部门成功');
    }

    #[OA\Get(
        path: '/sys/role/{id}/depts',
        summary: '获取角色数据权限部门',
        tags: ['角色']
    )]
    #[Permission(title: '查看角色数据权限', code: 'sys:role:get-depts', action: 'query')]
    #[DataResponse(schema: RoleDeptResponse::class)]
    public function getDepts(int $id): Response
    {
        $this->validator->scene('show')->setPath()->check();
        $depts = $this->roleService->getRoleDepts($id);
        return R::data($depts, '获取数据权限部门成功');
    }
}
