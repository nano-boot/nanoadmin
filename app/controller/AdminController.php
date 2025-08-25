<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\service\AdminService;

/**
 * 管理员控制器
 */
class AdminController
{
    private AdminService $adminService;

    public function __construct()
    {
        $this->adminService = new AdminService();
    }

    /**
     * 获取管理员列表
     * GET /sys/admins
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $params = [
            'page' => (int)$request->get('page', 1),
            'limit' => (int)$request->get('limit', 20),
            'keyword' => $request->get('keyword', ''),
            'status' => $request->get('status', ''),
        ];
        $result = $this->adminService->getAdminList($params);
        return R::paginate($result, '获取管理员列表成功');
    }

    /**
     * 获取管理员详情
     * GET /sys/admins/{id}
     * @param Request $request
     * @return Response
     */
    public function show(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return R::error('管理员ID无效', Code::PARAMETER_ERROR->value);
            }

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
    public function store(Request $request)
    {
        try {
            $data = [
                'username' => $request->post('username', ''),
                'password' => $request->post('password', ''),
                'nickname' => $request->post('nickname', ''),
                'phone' => $request->post('phone', ''),
                'email' => $request->post('email', ''),
                'avatar' => $request->post('avatar', ''),
                'status' => (int)$request->post('status', 1),
            ];

            // 参数验证
            $validation = $this->validateAdminData($data, true);
            if ($validation !== true) {
                return R::error((string)$validation, Code::PARAMETER_ERROR->value);
            }

            $admin = $this->adminService->createAdmin($data);

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
     */
    public function update(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return R::error('管理员ID无效', Code::PARAMETER_ERROR->value);
            }

            $data = [
                'username' => $request->post('username', ''),
                'password' => $request->post('password', ''),
                'nickname' => $request->post('nickname', ''),
                'phone' => $request->post('phone', ''),
                'email' => $request->post('email', ''),
                'avatar' => $request->post('avatar', ''),
                'status' => (int)$request->post('status', 1),
            ];

            // 过滤空密码
            if (empty($data['password'])) {
                unset($data['password']);
            }

            // 参数验证
            $validation = $this->validateAdminData($data, false);
            if ($validation !== true) {
                return R::error((string)$validation, Code::PARAMETER_ERROR->value);
            }

            $admin = $this->adminService->updateAdmin($id, $data);

            return R::updated($admin, '更新管理员成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('更新管理员失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 删除管理员
     * DELETE /sys/admins/{id}
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return R::error('管理员ID无效', Code::PARAMETER_ERROR->value);
            }

            $this->adminService->deleteAdmin($id);
            return R::deleted('删除管理员成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('删除管理员失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 为管理员分配角色
     * POST /sys/admins/{id}/roles
     * @param Request $request
     * @return Response
     */
    public function assignRoles(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return R::error('管理员ID无效', Code::PARAMETER_ERROR->value);
            }

            $roleIds = $request->post('role_ids', []);
            
            if (!is_array($roleIds)) {
                return R::error('角色ID列表格式错误', Code::PARAMETER_ERROR->value);
            }

            // 转换为整数数组
            $roleIds = array_map('intval', $roleIds);
            $roleIds = array_filter($roleIds, function($id) {
                return $id > 0;
            });

            $result = $this->adminService->assignRoles($id, $roleIds);
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
    public function getRoles(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                return R::error('管理员ID无效', Code::PARAMETER_ERROR->value);
            }
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
    public function batchDestroy(Request $request)
    {
        try {
            $ids = $request->post('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                return R::error('请选择要删除的管理员', Code::PARAMETER_ERROR->value);
            }

            // 转换为整数数组
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function($id) {
                return $id > 0;
            });

            if (empty($ids)) {
                return R::error('管理员ID列表无效', Code::PARAMETER_ERROR->value);
            }

            $result = $this->adminService->batchDeleteAdmins($ids);
            return R::success($result, '批量删除管理员成功');

        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('批量删除管理员失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 验证管理员数据
     * @param array $data
     * @param bool $isCreate
     * @return string|true
     */
    private function validateAdminData(array $data, bool $isCreate = false)
    {
        // 用户名验证
        if (empty($data['username'])) {
            return '用户名不能为空';
        }

        if (strlen($data['username']) < 3 || strlen($data['username']) > 20) {
            return '用户名长度必须在3-20个字符之间';
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            return '用户名只能包含字母、数字和下划线';
        }

        // 密码验证（创建时必须，更新时可选）
        if ($isCreate && empty($data['password'])) {
            return '密码不能为空';
        }

        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6 || strlen($data['password']) > 20) {
                return '密码长度必须在6-20个字符之间';
            }
        }

        // 昵称验证
        if (empty($data['nickname'])) {
            return '昵称不能为空';
        }

        if (strlen($data['nickname']) > 50) {
            return '昵称长度不能超过50个字符';
        }

        // 手机号验证
        if (!empty($data['phone'])) {
            if (!preg_match('/^1[3-9]\d{9}$/', $data['phone'])) {
                return '手机号格式不正确';
            }
        }

        // 邮箱验证
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return '邮箱格式不正确';
            }
        }

        // 状态验证
        if (!in_array($data['status'], [0, 1])) {
            return '状态值无效';
        }

        return true;
    }
}