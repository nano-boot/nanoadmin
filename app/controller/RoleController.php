<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiResponse;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\service\RoleService;

/**
 * 角色控制器
 */
class RoleController
{
    private RoleService $roleService;

    public function __construct()
    {
        $this->roleService = new RoleService();
    }

    /**
     * 获取角色列表
     * GET /sys/roles
     * @param Request $request
     * @return Response
     */
    public function list(Request $request)
    {
    }

    /**
     * 获取角色详情
     * GET /sys/roles/{id}
     * @param Request $request
     * @return Response
     */
    public function show(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '角色ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $role = $this->roleService->getRoleById($id);

            $response = ApiResponse::success($role, '获取角色详情成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = Code::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(Code::SYSTEM_ERROR, '获取角色详情失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 创建角色
     * POST /sys/roles
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        try {
            $data = [
                'code' => $request->post('code', ''),
                'name' => $request->post('name', ''),
                'description' => $request->post('description', ''),
                'sort' => (int)$request->post('sort', 0),
                'status' => (int)$request->post('status', 1),
            ];

            // 参数验证
            $validation = $this->validateRoleData($data, true);
            if ($validation !== true) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, $validation);
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $role = $this->roleService->createRole($data);

            $response = ApiResponse::created($role, '创建角色成功');
            return new Response(201, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = Code::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(Code::SYSTEM_ERROR, '创建角色失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 更新角色
     * PUT /sys/roles/{id}
     * @param Request $request
     * @return Response
     */
    public function update(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '角色ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $data = [
                'code' => $request->post('code', ''),
                'name' => $request->post('name', ''),
                'description' => $request->post('description', ''),
                'sort' => (int)$request->post('sort', 0),
                'status' => (int)$request->post('status', 1),
            ];

            // 参数验证
            $validation = $this->validateRoleData($data, false);
            if ($validation !== true) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, $validation);
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $role = $this->roleService->updateRole($id, $data);

            $response = ApiResponse::updated($role, '更新角色成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = Code::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(Code::SYSTEM_ERROR, '更新角色失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 删除角色
     * DELETE /sys/roles/{id}
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '角色ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $this->roleService->deleteRole($id);

            $response = ApiResponse::deleted('删除角色成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = Code::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(Code::SYSTEM_ERROR, '删除角色失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 为角色分配权限
     * POST /sys/roles/{id}/permissions
     * @param Request $request
     * @return Response
     */
    public function assignPermissions(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '角色ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $permissionIds = $request->post('permission_ids', []);
            
            if (!is_array($permissionIds)) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '权限ID列表格式错误');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            // 转换为整数数组
            $permissionIds = array_map('intval', $permissionIds);
            $permissionIds = array_filter($permissionIds, function($id) {
                return $id > 0;
            });

            $result = $this->roleService->assignPermissions($id, $permissionIds);

            $response = ApiResponse::success($result, '分配权限成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = Code::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(Code::SYSTEM_ERROR, '分配权限失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 为角色分配菜单
     * POST /sys/roles/{id}/menus
     * @param Request $request
     * @return Response
     */
    public function assignMenus(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '角色ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $menuIds = $request->post('menu_ids', []);
            
            if (!is_array($menuIds)) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '菜单ID列表格式错误');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            // 转换为整数数组
            $menuIds = array_map('intval', $menuIds);
            $menuIds = array_filter($menuIds, function($id) {
                return $id > 0;
            });

            $result = $this->roleService->assignMenus($id, $menuIds);

            $response = ApiResponse::success($result, '分配菜单成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = Code::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(Code::SYSTEM_ERROR, '分配菜单失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 获取角色的权限列表
     * GET /sys/roles/{id}/permissions
     * @param Request $request
     * @return Response
     */
    public function getPermissions(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '角色ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $permissions = $this->roleService->getRolePermissions($id);

            $response = ApiResponse::success($permissions, '获取角色权限成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = Code::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(Code::SYSTEM_ERROR, '获取角色权限失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 获取角色的菜单列表
     * GET /sys/roles/{id}/menus
     * @param Request $request
     * @return Response
     */
    public function getMenus(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '角色ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $menus = $this->roleService->getRoleMenus($id);

            $response = ApiResponse::success($menus, '获取角色菜单成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = Code::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(Code::SYSTEM_ERROR, '获取角色菜单失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 批量删除角色
     * DELETE /sys/roles/batch
     * @param Request $request
     * @return Response
     */
    public function batchDestroy(Request $request)
    {
        try {
            $ids = $request->post('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '请选择要删除的角色');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            // 转换为整数数组
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function($id) {
                return $id > 0;
            });

            if (empty($ids)) {
                $response = ApiResponse::error(Code::PARAMETER_ERROR, '角色ID列表无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $result = $this->roleService->batchDeleteRoles($ids);

            $response = ApiResponse::success($result, '批量删除角色成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = Code::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(Code::SYSTEM_ERROR, '批量删除角色失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 验证角色数据
     * @param array $data
     * @param bool $isCreate
     * @return string|true
     */
    private function validateRoleData(array $data, bool $isCreate = false)
    {
        // 角色代码验证
        if (empty($data['code'])) {
            return '角色代码不能为空';
        }

        if (strlen($data['code']) < 2 || strlen($data['code']) > 50) {
            return '角色代码长度必须在2-50个字符之间';
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $data['code'])) {
            return '角色代码只能包含字母、数字、下划线和连字符';
        }

        // 角色名称验证
        if (empty($data['name'])) {
            return '角色名称不能为空';
        }

        if (strlen($data['name']) > 100) {
            return '角色名称长度不能超过100个字符';
        }

        // 描述验证
        if (!empty($data['description']) && strlen($data['description']) > 500) {
            return '角色描述长度不能超过500个字符';
        }

        // 排序值验证
        if ($data['sort'] < 0 || $data['sort'] > 9999) {
            return '排序值必须在0-9999之间';
        }

        // 状态验证
        if (!in_array($data['status'], [0, 1])) {
            return '状态值无效';
        }

        return true;
    }
}