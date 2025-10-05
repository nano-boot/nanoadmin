<?php

namespace plugin\theadmin\app\controller;

use DI\Attribute\Inject;
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
class AdminController
{
    /**
     * 管理员服务实例
     * @var AdminService
     */
    private AdminService $adminService;

    /**
     * 构造函数 - 使用依赖注入
     * @param AdminService $adminService 管理员服务实例
     */
    public function __construct(AdminService $adminService)
    {
        new AdminValidator();
        $this->adminService = $adminService;
    }

    /**
     * 获取管理员列表
     * GET /sys/admins
     * @param Request $request
     * @return Response
     */
    public function list(Request $request): Response
    {
        try {
            // 参数验证
//            $params = AdminValidator::validateListParams($request->all());
            $params = $request->all();
            // 设置默认值
            $params = array_merge([
                'page' => 1,
                'limit' => 20,
                'keyword' => '',
                'status' => '',
            ], $params);

            $result = $this->adminService->getAdminList($params);
            return R::paginate($result, '获取管理员列表成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取管理员列表失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 获取管理员详情
     * GET /sys/admins/{id}
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        try {
            // 参数验证
//            $id = AdminValidator::validateId($request->get('id', 0));
            $id = $request->get('id', 0);
            $admin = $this->adminService->getAdminById($id);

            return R::success($admin, '获取管理员详情成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取管理员详情失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 创建管理员
     * POST /sys/admins
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        try {
            // 获取请求数据
            $requestData = [
                'username' => $request->post('username', ''),
                'password' => $request->post('password', ''),
                'nickname' => $request->post('nickname', ''),
                'phone' => $request->post('phone', ''),
                'email' => $request->post('email', ''),
                'avatar' => $request->post('avatar', ''),
                'status' => (int)$request->post('status', 1),
                'gender' => $request->post('gender', ''),
            ];

            $admin = $this->adminService->createAdmin($requestData);

            return R::created($admin, '创建管理员成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('创建管理员失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 更新管理员
     * PUT /sys/admins/{id}
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function update(Request $request): Response
    {
        $id = (int)$request->input('id', 0);

        $requestData = $request->only([
            'username','nickname', 'password', 'phone', 'email', 'avatar', 'gender', 'status','admin'
        ]);
        $admin = $this->adminService->updateAdmin($id, $requestData);

        return R::data($admin, '更新管理员成功');
    }

    /**
     * 删除管理员
     * DELETE /sys/admins/{id}
     * @param int $id
     * @return Response
     * @throws ApiException
     */
    public function destroy(int $id): Response
    {
        $this->adminService->deleteAdmin($id);
        return R::deleted('删除管理员成功');
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
     * 批量删除管理员
     * DELETE /sys/admins/batch
     * @param Request $request
     * @return Response
     */
    public function batchDestroy(Request $request): Response
    {
        try {
            // 验证批量ID数据
            $requestData = [
                'ids' => $request->post('ids', [])
            ];
            $validator = new AdminValidator();
            $validatedData = $validator->validateBatchIds($requestData);

            $result = $this->adminService->batchDeleteAdmins($validatedData['ids']);
            return R::success($result, '批量删除管理员成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('批量删除管理员失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

}