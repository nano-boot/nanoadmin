<?php

namespace plugin\theadmin\app\controller;

use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiResponse;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\ErrorCode;
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
    public function index(Request $request)
    {
        try {
            $params = [
                'page' => (int)$request->get('page', 1),
                'limit' => (int)$request->get('limit', 20),
                'keyword' => $request->get('keyword', ''),
                'status' => $request->get('status', ''),
            ];

            $result = $this->adminService->getAdminList($params);

            $response = ApiResponse::paginate($result, '获取管理员列表成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取管理员列表失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
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
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '管理员ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $admin = $this->adminService->getAdminById($id);

            $response = ApiResponse::success($admin, '获取管理员详情成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取管理员详情失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
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
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, $validation);
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $admin = $this->adminService->createAdmin($data);

            $response = ApiResponse::created($admin, '创建管理员成功');
            return new Response(201, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '创建管理员失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
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
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '管理员ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
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
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, $validation);
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $admin = $this->adminService->updateAdmin($id, $data);

            $response = ApiResponse::updated($admin, '更新管理员成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '更新管理员失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
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
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '管理员ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $this->adminService->deleteAdmin($id);

            $response = ApiResponse::deleted('删除管理员成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '删除管理员失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
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
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '管理员ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $roleIds = $request->post('role_ids', []);
            
            if (!is_array($roleIds)) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '角色ID列表格式错误');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            // 转换为整数数组
            $roleIds = array_map('intval', $roleIds);
            $roleIds = array_filter($roleIds, function($id) {
                return $id > 0;
            });

            $result = $this->adminService->assignRoles($id, $roleIds);

            $response = ApiResponse::success($result, '分配角色成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '分配角色失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
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
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '管理员ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $roles = $this->adminService->getAdminRoles($id);

            $response = ApiResponse::success($roles, '获取管理员角色成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取管理员角色失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
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
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '请选择要删除的管理员');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            // 转换为整数数组
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function($id) {
                return $id > 0;
            });

            if (empty($ids)) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '管理员ID列表无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $result = $this->adminService->batchDeleteAdmins($ids);

            $response = ApiResponse::success($result, '批量删除管理员成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '批量删除管理员失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
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