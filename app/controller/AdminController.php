<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\common\R;
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
 */
#[OA\Tag(name: '管理员', description: '系统管理员管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class AdminController extends CommonController
{
    private AdminService $adminService;

    /**
     * 查询参数校验器
     * 启用父类 CommonController::validateQuery()，
     * 用于 page 接口的分页/搜索参数校验（'page' 场景）。
     */
    protected ?string $queryValidator = AdminValidator::class;

    /**
     * 创建参数校验器
     * 启用父类 CommonController::validateCreate()，
     * 由父类负责 new AdminValidator()->validated()，
     * 内部推断到 create 场景并完成校验。
     */
    protected ?string $createValidator = AdminValidator::class;

    /**
     * 更新参数校验器
     * 启用父类 CommonController::validateUpdate()。
     */
    protected ?string $updateValidator = AdminValidator::class;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    protected function getService(): AdminService
    {
        return $this->adminService;
    }

    protected function getModelName(): string
    {
        return 'Admin';
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
        $this->validateQuery($request);
        return parent::page($request);
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
        (new AdminValidator())->validateId($id);
        return parent::show($id);
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
        $data = $this->validateCreate($request);
        return parent::create($request);
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
        $this->validateUpdate($request);
        return parent::update($request, $id);
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
        (new AdminValidator())->validateId($id);
        return parent::destroy($id);
    }

    #[OA\Delete(
        path: '/sys/admin/batch',
        summary: '批量删除管理员',
        tags: ['管理员']
    )]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        (new AdminValidator())->validateBatchIds($request->post());
        return parent::batchDestroy($request);
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
        try {
            $data = $this->validateRoleAssign($request);
            $result = $this->adminService->assignRoles($id, $data['role_ids']);
            return R::success($result, '分配角色成功');
        } catch (\Exception $e) {
            return R::error($e->getMessage(), Code::SYSTEM_ERROR->value);
        }
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
        try {
            $roles = $this->adminService->getAdminRoles($id);
            return R::success($roles, '获取管理员角色成功');
        } catch (\Exception $e) {
            return R::error($e->getMessage(), Code::SYSTEM_ERROR->value);
        }
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
        try {
            $currentUser = $request->admin;
            if (!$currentUser) {
                return R::error('用户未登录', 401);
            }

            $data = $this->validatePasswordUpdate($request);

            // 验证旧密码是否正确
            $admin = $this->adminService->getById($currentUser->id);
            if (!$admin->verifyPassword($data['old_password'])) {
                return R::error('旧密码不正确', 422);
            }

            $this->adminService->resetAdminPassword($currentUser->id, $data['password']);

            return R::success(null, '密码修改成功');
        } catch (\Exception $e) {
            return R::error('密码修改失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
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
        try {
            $currentUser = $request->admin;
            if (!$currentUser) {
                return R::error('用户未登录', 401);
            }

            $data = $this->validateProfileUpdate($request);

            // 更新用户资料
            $admin = $this->adminService->update($currentUser->id, $data);

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
        } catch (\Exception $e) {
            return R::error('更新用户资料失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 验证角色分配数据
     */
    private function validateRoleAssign(Request $request): array
    {
        $validator = new AdminValidator();
        return $validator->validateRoleAssignData($request->post());
    }

    /**
     * 验证密码修改数据
     */
    private function validatePasswordUpdate(Request $request): array
    {
        $validator = new AdminValidator();
        return $validator->validateData($request->post(), 'updateCurrentPassword');
    }

    /**
     * 验证个人资料更新数据
     */
    private function validateProfileUpdate(Request $request): array
    {
        $validator = new AdminValidator();
        return $validator->validateData($request->post(), 'updateProfile');
    }
}
