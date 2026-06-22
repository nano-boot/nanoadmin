<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\admin\AdminQuery;
use plugin\nanoadmin\app\schema\admin\AdminRequest;
use plugin\nanoadmin\app\schema\admin\AdminResponse;
use plugin\nanoadmin\app\schema\admin\AdminRoleRequest;
use plugin\nanoadmin\app\schema\admin\AdminPasswordRequest;
use plugin\nanoadmin\app\schema\admin\AdminProfileRequest;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\validator\admin\AdminValidator;
use plugin\nanoadmin\app\service\AdminService;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 管理员控制器
 *
 * 采用「薄 Controller」模式：
 * - Controller 只负责接收请求、调用验证器、调用 Service、返回响应
 * - 异常由全局异常处理器统一处理
 * - 业务逻辑全部在 Service 层
 */
#[OA\Tag(name: '管理员', description: '系统管理员管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class AdminController extends BaseController
{
    private AdminService $service;
    private AdminValidator $validator;

    public function __construct(AdminService $service, AdminValidator $validator)
    {
        $this->service = $service;
        $this->validator = $validator;
    }

    #[OA\Get(
        path: '/sys/admin',
        summary: '管理员列表',
        tags: ['管理员'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => AdminQuery::class]
    )]
    #[PageResponse(schema: AdminResponse::class)]
    public function page(Request $request): Response
    {
        $params = $this->validator->scenePage()->setGet()->check();
        return R::paginate($this->service->getPage($params));
    }

    #[OA\Get(
        path: '/sys/admin/{id}',
        summary: '管理员详情',
        tags: ['管理员'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '管理员ID'],
        ]]
    )]
    #[DataResponse(schema: AdminResponse::class)]
    public function show(int $id = 0): Response
    {
        $this->validator->setData(['id' => $id])->sceneShow()->check();
        return R::success($this->service->getById($id), '获取详情成功');
    }

    #[OA\Post(
        path: '/sys/admin',
        summary: '创建管理员',
        tags: ['管理员'],
        x: [OpenApiModifier::X_REQUEST_BODY => AdminRequest::class]
    )]
    #[DataResponse()]
    public function create(Request $request): Response
    {
        $data = $this->validator->sceneCreate()->setPost()->check();
        return R::created($this->service->create($data));
    }

    #[OA\Put(
        path: '/sys/admin/{id}',
        summary: '更新管理员',
        tags: ['管理员'],
        x: [
            OpenApiModifier::X_PATH_PARAMETERS => [
                'id' => ['type' => 'integer', 'description' => '管理员ID'],
            ],
            OpenApiModifier::X_REQUEST_BODY => AdminRequest::class
        ]
    )]
    #[DataResponse()]
    public function update(Request $request, int $id): Response
    {
        $data = $request->post();
        // $data = $this->validator->setScene('update')->setPost()->check();
        $this->validator->make($data)->withScene('update')->validate();
        return R::data($this->service->update($id, $data), '更新成功');
    }

    #[OA\Delete(
        path: '/sys/admin/{id}',
        summary: '删除管理员',
        tags: ['管理员'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '管理员ID'],
        ]]
    )]
    #[DataResponse()]
    public function destroy(int $id): Response
    {
        $this->validator->setData(['id' => $id])->sceneShow()->check();
        $this->service->delete($id);
        return R::success(null, '删除成功');
    }

    #[OA\Delete(
        path: '/sys/admin/batch',
        summary: '批量删除管理员',
        tags: ['管理员']
    )]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        $data = $this->validator->sceneBatchDelete()->setPost()->check();
        $result = $this->service->batchDeleteAdmins($data['ids']);
        return R::success($result, '批量删除管理员成功');
    }

    #[OA\Post(
        path: '/sys/admin/{id}/roles',
        summary: '分配角色',
        description: '为管理员分配角色',
        tags: ['管理员'],
        x: [
            OpenApiModifier::X_PATH_PARAMETERS => [
                'id' => ['type' => 'integer', 'description' => '管理员ID'],
            ],
            OpenApiModifier::X_REQUEST_BODY => AdminRoleRequest::class
        ]
    )]
    #[DataResponse()]
    public function assignRoles(Request $request, int $id): Response
    {
        $this->validator->setData(['id' => $id])->sceneShow()->check();
        $data = $this->validator->sceneAssignRoles()->setPost()->check();
        $this->service->assignRoles($id, $data['role_ids']);
        return R::success(null, '分配角色成功');
    }

    #[OA\Get(
        path: '/sys/admin/{id}/roles',
        summary: '获取管理员角色',
        tags: ['管理员'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '管理员ID'],
        ]]
    )]
    #[DataResponse()]
    public function getRoles(int $id): Response
    {
        $this->validator->setData(['id' => $id])->sceneShow()->check();
        $roles = $this->service->getAdminRoles($id);
        return R::success($roles, '获取管理员角色成功');
    }

    #[OA\Put(
        path: '/sys/admin/password',
        summary: '修改当前用户密码',
        tags: ['管理员'],
        x: [OpenApiModifier::X_REQUEST_BODY => AdminPasswordRequest::class]
    )]
    #[DataResponse()]
    public function updateCurrentPassword(Request $request): Response
    {
        $currentUser = $request->admin;
        if (!$currentUser) {
            throw new ApiException('用户未登录', Code::UNAUTHORIZED->value);
        }

        $data = $this->validator->sceneUpdateCurrentPassword()->setPost()->check();

        $admin = $this->service->getById($currentUser->id);
        if (!$admin->verifyPassword($data['old_password'])) {
            throw new ApiException('旧密码不正确', Code::PARAMETER_ERROR->value);
        }

        $this->service->resetAdminPassword($currentUser->id, $data['password']);
        return R::success(null, '密码修改成功');
    }

    #[OA\Put(
        path: '/sys/admin/info',
        summary: '更新当前用户资料',
        tags: ['管理员'],
        x: [OpenApiModifier::X_REQUEST_BODY => AdminProfileRequest::class]
    )]
    #[DataResponse(schema: AdminResponse::class)]
    public function updateProfile(Request $request): Response
    {
        $currentUser = $request->admin;
        if (!$currentUser) {
            throw new ApiException('用户未登录', Code::UNAUTHORIZED->value);
        }

        $data = $this->validator
            ->withContext(['excludeId' => $currentUser->id])
            ->sceneUpdateProfile()
            ->setPost()
            ->check();
        $admin = $this->service->update($currentUser->id, $data);

        return R::success([
            'id' => $admin->id,
            'username' => $admin->username,
            'nickname' => $admin->nickname,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'avatar' => $admin->avatar,
            'status' => $admin->status,
            'gender' => $admin->gender,
            'roles' => $admin->roles->pluck('name')->toArray()
        ], '更新用户资料成功');
    }
}
