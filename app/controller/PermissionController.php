<?php

namespace plugin\theadmin\app\controller;

use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiResponse;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\ErrorCode;
use plugin\theadmin\app\service\PermissionService;

/**
 * 权限控制器
 */
class PermissionController
{
    private PermissionService $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService();
    }

    /**
     * 获取权限列表
     * GET /sys/permissions
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
                'resource_type' => $request->get('resource_type', ''),
                'action_type' => $request->get('action_type', ''),
            ];

            $result = $this->permissionService->getPermissionList($params);

            $response = ApiResponse::paginate($result, '获取权限列表成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取权限列表失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 获取权限详情
     * GET /sys/permissions/{id}
     * @param Request $request
     * @return Response
     */
    public function show(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '权限ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $permission = $this->permissionService->getPermissionById($id);

            $response = ApiResponse::success($permission, '获取权限详情成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取权限详情失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 创建权限
     * POST /sys/permissions
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        try {
            $data = [
                'code' => $request->post('code', ''),
                'name' => $request->post('name', ''),
                'resource_type' => $request->post('resource_type', ''),
                'action_type' => $request->post('action_type', ''),
                'description' => $request->post('description', ''),
            ];

            // 参数验证
            $validation = $this->validatePermissionData($data, true);
            if ($validation !== true) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, $validation);
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $permission = $this->permissionService->createPermission($data);

            $response = ApiResponse::created($permission, '创建权限成功');
            return new Response(201, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '创建权限失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 更新权限
     * PUT /sys/permissions/{id}
     * @param Request $request
     * @return Response
     */
    public function update(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '权限ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $data = [
                'code' => $request->post('code', ''),
                'name' => $request->post('name', ''),
                'resource_type' => $request->post('resource_type', ''),
                'action_type' => $request->post('action_type', ''),
                'description' => $request->post('description', ''),
            ];

            // 参数验证
            $validation = $this->validatePermissionData($data, false);
            if ($validation !== true) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, $validation);
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $permission = $this->permissionService->updatePermission($id, $data);

            $response = ApiResponse::updated($permission, '更新权限成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '更新权限失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 删除权限
     * DELETE /sys/permissions/{id}
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request)
    {
        try {
            $id = (int)$request->get('id', 0);
            
            if ($id <= 0) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '权限ID无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $this->permissionService->deletePermission($id);

            $response = ApiResponse::deleted('删除权限成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '删除权限失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 批量删除权限
     * DELETE /sys/permissions/batch
     * @param Request $request
     * @return Response
     */
    public function batchDestroy(Request $request)
    {
        try {
            $ids = $request->post('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '请选择要删除的权限');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            // 转换为整数数组
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function($id) {
                return $id > 0;
            });

            if (empty($ids)) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '权限ID列表无效');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $result = $this->permissionService->batchDeletePermissions($ids);

            $response = ApiResponse::success($result, '批量删除权限成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '批量删除权限失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 验证权限数据
     * @param array $data
     * @param bool $isCreate
     * @return string|true
     */
    private function validatePermissionData(array $data, bool $isCreate = false)
    {
        // 权限代码验证
        if (empty($data['code'])) {
            return '权限代码不能为空';
        }

        if (strlen($data['code']) < 2 || strlen($data['code']) > 100) {
            return '权限代码长度必须在2-100个字符之间';
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $data['code'])) {
            return '权限代码只能包含字母、数字、下划线、点和连字符';
        }

        // 权限名称验证
        if (empty($data['name'])) {
            return '权限名称不能为空';
        }

        if (strlen($data['name']) > 100) {
            return '权限名称长度不能超过100个字符';
        }

        // 资源类型验证
        if (empty($data['resource_type'])) {
            return '资源类型不能为空';
        }

        if (strlen($data['resource_type']) > 50) {
            return '资源类型长度不能超过50个字符';
        }

        // 操作类型验证
        if (empty($data['action_type'])) {
            return '操作类型不能为空';
        }

        if (strlen($data['action_type']) > 50) {
            return '操作类型长度不能超过50个字符';
        }

        // 描述验证
        if (!empty($data['description']) && strlen($data['description']) > 500) {
            return '权限描述长度不能超过500个字符';
        }

        return true;
    }
}