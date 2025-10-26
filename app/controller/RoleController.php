<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use plugin\theadmin\app\validator\RoleValidator;
use Webman\Http\Request;
use support\Response;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\service\RoleService;

/**
 * 角色控制器
 */
class RoleController
{
    private RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        new RoleValidator();
        $this->roleService = $roleService;
    }

    /**
     * 获取角色列表
     * GET /sys/roles
     * @param Request $request
     * @return Response
     */
    public function list(Request $request): Response
    {
        $params = [
            'page' => (int)$request->get('page', 1),
            'limit' => (int)$request->get('limit', 20),
            'keyword' => $request->get('keyword', ''),
            'status' => $request->get('status', ''),
        ];
        $result = $this->roleService->getRoleList($params);
        return R::paginate($result, '获取角色列表成功');
    }

    /**
     * 获取角色详情
     * GET /sys/roles/{id}
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function show(Request $request): Response
    {
        $id = (int)$request->get('id', 0);
        if ($id <= 0) {
            $response = R::error('角色ID无效',Code::PARAMETER_ERROR->value);
            return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
        }
        $role = $this->roleService->getRoleById($id);
        return R::data($role, '获取角色详情成功');
    }

    /**
     * 创建角色
     * POST /sys/roles
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function store(Request $request): Response
    {
        return R::data($this->roleService->createRole($request->only([
            'code', 'name', 'description', 'sort', 'status']
        )));
    }

    /**
     * 更新角色
     * PUT /sys/roles/{id}
     * @param Request $request
     * @param int $id
     * @return Response
     * @throws ApiException
     */
    public function update(Request $request, int $id): Response
    {
        $this->roleService->updateRole($id, $request->only(['name', 'description', 'sort', 'status']));
        return R::ok();
    }

    /**
     * 删除角色
     * DELETE /sys/roles/{id}
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function destroy(Request $request): Response
    {

        $id = (int)$request->get('id', 0);
        if ($id <= 0) {
            return R::error(Code::PARAMETER_ERROR);
        }
        $this->roleService->deleteRole($id);
        return R::deleted('删除角色成功');
    }

    /**
     * 为角色分配权限（接收菜单ID和权限标识）
     * POST /sys/role/{id}/permissions
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function assignPermissions($id, Request $request): Response
    {
        $id = (int)$id;
        if ($id <= 0) {
            return R::error('角色ID无效', Code::PARAMETER_ERROR->value);
        }   
        // 获取菜单ID和权限标识
        $menuIds = $request->post('menuIds', []);
        $authMarks = $request->post('authMarks', []);
        
        // 验证数据格式
        if (!is_array($menuIds)) {
            return R::error('菜单ID列表格式错误', Code::PARAMETER_ERROR->value);
        }
        
        if (!is_array($authMarks)) {
            return R::error('权限标识列表格式错误', Code::PARAMETER_ERROR->value);
        }

        // 转换菜单ID为整数数组
        $menuIds = array_map('intval', $menuIds);
        $menuIds = array_filter($menuIds, function($id) {
            return $id > 0;
        });
        
        // 过滤权限标识（确保是字符串）
        $authMarks = array_filter($authMarks, function($mark) {
            return is_string($mark) && !empty($mark);
        });
        
        // 调用服务层
        $result = $this->roleService->assignPermissions($id, [
            'menuIds' => array_values($menuIds),
            'authMarks' => array_values($authMarks)
        ]);
        
        return R::data($result, '分配权限成功');
    }

    /**
     * 为角色分配菜单
     * POST /sys/roles/{id}/menus
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function assignMenus(Request $request)
    {
        $id = (int)$request->get('id', 0);

        if ($id <= 0) {
            return R::error('角色ID无效', Code::PARAMETER_ERROR->value);
        }

        $menuIds = $request->post('menu_ids', []);

        if (!is_array($menuIds)) {
            return R::error('菜单ID列表格式错误', Code::PARAMETER_ERROR->value);
        }

        // 转换为整数数组
        $menuIds = array_map('intval', $menuIds);
        $menuIds = array_filter($menuIds, function($id) {
            return $id > 0;
        });

        $result = $this->roleService->assignMenus($id, $menuIds);
        return R::data($result, '分配菜单成功');
    }

    /**
     * 获取角色的权限列表
     * GET /sys/roles/{id}/permissions
     * @param $id
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function getPermissions($id): Response
    {
            $id = (int)$id;
            if ($id <= 0) {
                return R::error('角色ID无效', Code::PARAMETER_ERROR->value);
            }
            $permissions = $this->roleService->getRolePermissions($id);
            return R::data($permissions, '获取角色权限成功');
    }

    /**
     * 获取角色的菜单列表
     * GET /sys/roles/{id}/menus
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function getMenus(Request $request)
    {
        $id = (int)$request->get('id', 0);

        if ($id <= 0) {
            $response = R::error(Code::PARAMETER_ERROR, '角色ID无效');
            return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
        }

        $menus = $this->roleService->getRoleMenus($id);
        return R::list($menus, '获取角色菜单成功');
    }

    /**
     * 批量删除角色
     * DELETE /sys/roles/batch
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function batchDestroy(Request $request): Response
    {
            $ids = $request->post('ids', []);
            
            if (!is_array($ids) || empty($ids)) {
                return R::error('请选择要删除的角色', Code::PARAMETER_ERROR->value);
            }

            // 转换为整数数组
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, function($id) {
                return $id > 0;
            });

            if (empty($ids)) {
                return R::error('角色ID列表无效', Code::PARAMETER_ERROR->value);
            }

            $result = $this->roleService->batchDeleteRoles($ids);
            return R::data($result, '批量删除角色成功');
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