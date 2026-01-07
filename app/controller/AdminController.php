<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\service\AdminService;
use plugin\theadmin\app\validator\AdminValidator;

/**
 * 管理员控制器
 */
class AdminController extends BaseController
{
    /**
     * 管理员服务实例
     * @var AdminService
     */
    private AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        new AdminValidator(true);
        $this->adminService = $adminService;
    }

    /**
     * 获取服务实例
     * @return AdminService
     */
    protected function getService(): AdminService
    {
        return $this->adminService;
    }

    /**
     * 获取模型名称
     * @return string
     */
    protected function getModelName(): string
    {
        return 'Admin';
    }

    /**
     * 为管理员分配角色
     * POST /sys/admins/{id}/roles
     * @param Request $request
     * @return Response
     */
    public function assignRoles(Request $request): Response
    {
        try {
            // 验证ID参数
            $validator = new AdminValidator();
            $id = $validator->validateId($request->get('id', 0));

            // 验证角色数据
            $requestData = [
                'role_ids' => $request->post('role_ids', [])
            ];
            $validatedData = $validator->validateRoleAssignData($requestData);

            $result = $this->adminService->assignRoles($id, $validatedData['role_ids']);
            return R::success($result, '分配角色成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('分配角色失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 获取管理员的角色列表
     * GET /sys/admins/{id}/roles
     * @param Request $request
     * @return Response
     */
    public function getRoles(Request $request): Response
    {
        try {
            // 验证ID参数
            $validator = new AdminValidator();
            $id = $validator->validateId($request->get('id', 0));

            $roles = $this->adminService->getAdminRoles($id);
            return R::success($roles, '获取管理员角色成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取管理员角色失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 更新当前用户密码
     * PUT /sys/admin/password
     * @param Request $request
     * @return Response
     */
    public function updateCurrentPassword(Request $request): Response
    {
        try {
            $currentUser = $request->admin;

            $requestData = $request->only([
                'old_password', 'password', 'confirm_password'
            ]);

            // 验证旧密码是否正确
            $admin = $this->adminService->getById($currentUser->id);
            if (!$admin->verifyPassword($requestData['old_password'])) {
                return R::error('旧密码不正确', 422);
            }

            $this->adminService->resetAdminPassword($currentUser->id, $requestData['password']);

            return R::success(null, '密码修改成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('密码修改失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 更新当前用户资料
     * PUT /sys/admin/info
     * @param Request $request
     * @return Response
     */
    public function updateProfile(Request $request): Response
    {

            // 获取当前登录用户
            $currentUser = $request->admin;
            if (!$currentUser) {
                return R::error('用户未登录', 401);
            }
           
            // 验证请求数据
            //  $validatedData = $this->adminValidator->validated();
            // $requestData = $request->only(['nickname', 'phone', 'email', 'avatar', 'gender']);
            // $adminValidator = new AdminValidator();
            // $validatedData = $adminValidator->validateProfileUpdateData($requestData, $currentUser->id);

            // 更新用户资料
            $admin = $this->adminService->update($currentUser->id, $request->post());

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