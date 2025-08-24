<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\Role;
use think\Paginator;

/**
 * 角色服务类
 */
class RoleService
{
    /**
     * 获取角色列表
     * @param array $params 查询参数
     * @return Paginator
     */
    public function getRoleList(array $params = []): Paginator
    {
        $roleModel = ModelFactory::role();
        
        // 构建查询条件
        $where = [];
        
        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $where['status'] = (bool)$params['status'];
        }
        
        // 排除已删除的记录
        $where['deleted'] = false;
        
        // 分页参数
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 15;
        
        // 搜索条件
        $searchParams = [];
        if (!empty($params['name'])) {
            $searchParams['name'] = $params['name'];
        }
        if (!empty($params['code'])) {
            $searchParams['code'] = $params['code'];
        }
        
        return $roleModel->getListWithCounts(array_merge($where, $searchParams), $page, $limit);
    }

    /**
     * 根据ID获取角色详情
     * @param int $id 角色ID
     * @return Role
     * @throws ApiException
     */
    public function getRoleById(int $id): Role
    {
        $roleModel = ModelFactory::role();
        $role = $roleModel->with(['permissions', 'menus'])->find($id);
        
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        return $role;
    }

    /**
     * 创建角色
     * @param array $data 角色数据
     * @return Role
     * @throws ApiException
     */
    public function createRole(array $data): Role
    {
        // 数据验证
        $this->validateRoleData($data, true);
        
        $roleModel = ModelFactory::role();
        
        // 检查角色代码是否已存在
        if ($roleModel->where('code', $data['code'])->find()) {
            throw new ApiException(Code::DUPLICATE_NAME, '角色代码已存在');
        }
        
        // 检查角色名称是否已存在
        if ($roleModel->where('name', $data['name'])->find()) {
            throw new ApiException(Code::DUPLICATE_NAME, '角色名称已存在');
        }
        
        // 设置默认值
        $data['status'] = $data['status'] ?? true;
        $data['deleted'] = false;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // 设置排序值
        if (!isset($data['sort'])) {
            $data['sort'] = $this->getNextSort();
        }
        
        // 创建角色
        $role = $roleModel->createRole($data);
        
        if (!$role) {
            throw new ApiException(Code::SYSTEM_ERROR, '创建角色失败');
        }
        
        return $role;
    }

    /**
     * 更新角色
     * @param int $id 角色ID
     * @param array $data 更新数据
     * @return bool
     * @throws ApiException
     */
    public function updateRole(int $id, array $data): bool
    {
        // 数据验证
        $this->validateRoleData($data, false);
        
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $role = $roleModel->find($id);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 检查角色代码是否已被其他角色使用
        if (!empty($data['code'])) {
            $existingRole = $roleModel->where('code', $data['code'])
                
                ->where('id', '<>', $id)
                ->find();
            if ($existingRole) {
                throw new ApiException(Code::DUPLICATE_NAME, '角色代码已存在');
            }
        }
        
        // 检查角色名称是否已被其他角色使用
        if (!empty($data['name'])) {
            $existingRole = $roleModel->where('name', $data['name'])
                
                ->where('id', '<>', $id)
                ->find();
            if ($existingRole) {
                throw new ApiException(Code::DUPLICATE_NAME, '角色名称已存在');
            }
        }
        
        // 更新时间
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // 更新角色
        $result = $roleModel->updateRole($id, $data);
        
        if (!$result) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新角色失败');
        }
        
        return true;
    }

    /**
     * 删除角色（软删除）
     * @param int $id 角色ID
     * @return bool
     * @throws ApiException
     */
    public function deleteRole(int $id): bool
    {
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $role = $roleModel->find($id);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 检查角色是否被使用
        if ($role->isUsed($id)) {
            throw new ApiException(Code::DATA_IN_USE, '角色正在使用中，无法删除');
        }
        
        // 软删除
        $result = $roleModel->destroy($id);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '删除角色失败');
        }
        
        return true;
    }

    /**
     * 启用/禁用角色
     * @param int $id 角色ID
     * @param bool $status 状态
     * @return bool
     * @throws ApiException
     */
    public function toggleRoleStatus(int $id, bool $status): bool
    {
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $role = $roleModel->find($id);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 更新状态
        $result = $roleModel->where('id', $id)->update([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新角色状态失败');
        }
        
        return true;
    }

    /**
     * 为角色分配权限
     * @param int $roleId 角色ID
     * @param array $permissionIds 权限ID数组
     * @return bool
     * @throws ApiException
     */
    public function assignPermissions(int $roleId, array $permissionIds): bool
    {
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $role = $roleModel->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 验证权限是否存在
        if (!empty($permissionIds)) {
            $permissionModel = ModelFactory::permission();
            $existingPermissions = $permissionModel->whereIn('id', $permissionIds)->where('status', true)->column('id');
            
            $invalidPermissionIds = array_diff($permissionIds, $existingPermissions);
            if (!empty($invalidPermissionIds)) {
                throw new ApiException(Code::PERMISSION_NOT_FOUND, '权限不存在: ' . implode(',', $invalidPermissionIds));
            }
        }
        
        // 分配权限
        $result = $role->assignPermissions($permissionIds);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '分配权限失败');
        }
        
        return true;
    }

    /**
     * 为角色分配菜单
     * @param int $roleId 角色ID
     * @param array $menuIds 菜单ID数组
     * @return bool
     * @throws ApiException
     */
    public function assignMenus(int $roleId, array $menuIds): bool
    {
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $role = $roleModel->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 验证菜单是否存在
        if (!empty($menuIds)) {
            $menuModel = ModelFactory::menu();
            $existingMenus = $menuModel->whereIn('id', $menuIds)->where('status', true)->column('id');
            
            $invalidMenuIds = array_diff($menuIds, $existingMenus);
            if (!empty($invalidMenuIds)) {
                throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在: ' . implode(',', $invalidMenuIds));
            }
        }
        
        // 分配菜单
        $result = $role->assignMenus($menuIds);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '分配菜单失败');
        }
        
        return true;
    }

    /**
     * 获取角色权限列表
     * @param int $roleId 角色ID
     * @return array
     * @throws ApiException
     */
    public function getRolePermissions(int $roleId): array
    {
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $role = $roleModel->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        return $role->getAllPermissions();
    }

    /**
     * 获取角色菜单列表
     * @param int $roleId 角色ID
     * @return array
     * @throws ApiException
     */
    public function getRoleMenus(int $roleId): array
    {
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $role = $roleModel->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        return $role->getAllMenus();
    }

    /**
     * 检查角色权限
     * @param int $roleId 角色ID
     * @param string $permission 权限代码
     * @return bool
     */
    public function checkRolePermission(int $roleId, string $permission): bool
    {
        try {
            $roleModel = ModelFactory::role();
            $role = $roleModel->find($roleId);
            
            if (!$role) {
                return false;
            }
            
            return $role->hasPermission($permission);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查角色菜单权限
     * @param int $roleId 角色ID
     * @param int $menuId 菜单ID
     * @return bool
     */
    public function checkRoleMenu(int $roleId, int $menuId): bool
    {
        try {
            $roleModel = ModelFactory::role();
            $role = $roleModel->find($roleId);
            
            if (!$role) {
                return false;
            }
            
            return $role->hasMenu($menuId);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 复制角色权限
     * @param int $sourceRoleId 源角色ID
     * @param int $targetRoleId 目标角色ID
     * @return bool
     * @throws ApiException
     */
    public function copyRolePermissions(int $sourceRoleId, int $targetRoleId): bool
    {
        $roleModel = ModelFactory::role();
        
        // 检查源角色是否存在
        $sourceRole = $roleModel->find($sourceRoleId);
        if (!$sourceRole) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '源角色不存在');
        }
        
        // 检查目标角色是否存在
        $targetRole = $roleModel->find($targetRoleId);
        if (!$targetRole) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '目标角色不存在');
        }
        
        // 复制权限
        $result = $sourceRole->copyPermissionsTo($targetRoleId);
        
        if (!$result) {
            throw new ApiException(Code::SYSTEM_ERROR, '复制角色权限失败');
        }
        
        return true;
    }

    /**
     * 复制角色菜单
     * @param int $sourceRoleId 源角色ID
     * @param int $targetRoleId 目标角色ID
     * @return bool
     * @throws ApiException
     */
    public function copyRoleMenus(int $sourceRoleId, int $targetRoleId): bool
    {
        $roleModel = ModelFactory::role();
        
        // 检查源角色是否存在
        $sourceRole = $roleModel->find($sourceRoleId);
        if (!$sourceRole) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '源角色不存在');
        }
        
        // 检查目标角色是否存在
        $targetRole = $roleModel->find($targetRoleId);
        if (!$targetRole) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '目标角色不存在');
        }
        
        // 复制菜单
        $result = $sourceRole->copyMenusTo($targetRoleId);
        
        if (!$result) {
            throw new ApiException(Code::SYSTEM_ERROR, '复制角色菜单失败');
        }
        
        return true;
    }

    /**
     * 获取角色统计信息
     * @param int $roleId 角色ID
     * @return array
     * @throws ApiException
     */
    public function getRoleStats(int $roleId): array
    {
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $role = $roleModel->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        return $role->getStats();
    }

    /**
     * 获取启用的角色列表（用于下拉选择）
     * @return array
     */
    public function getEnabledRoles(): array
    {
        $roleModel = ModelFactory::role();
        $roles = $roleModel->getEnabledList();
        
        $result = [];
        foreach ($roles as $role) {
            $result[] = [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'description' => $role->description,
                'sort' => $role->sort
            ];
        }
        
        return $result;
    }

    /**
     * 调整角色排序
     * @param int $roleId 角色ID
     * @param int $sort 排序值
     * @return bool
     * @throws ApiException
     */
    public function updateRoleSort(int $roleId, int $sort): bool
    {
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $role = $roleModel->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 验证排序值
        if ($sort < 0) {
            throw new ApiException(Code::INVALID_SORT_ORDER, '排序值不能为负数');
        }
        
        // 更新排序
        $result = $roleModel->where('id', $roleId)->update([
            'sort' => $sort,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新角色排序失败');
        }
        
        return true;
    }

    /**
     * 批量删除角色
     * @param array $ids 角色ID数组
     * @return bool
     * @throws ApiException
     */
    public function batchDeleteRoles(array $ids): bool
    {
        if (empty($ids)) {
            throw new ApiException(Code::PARAMETER_ERROR, '请选择要删除的角色');
        }
        
        $roleModel = ModelFactory::role();
        
        // 检查角色是否存在
        $existingRoles = $roleModel->whereIn('id', $ids)->select();
        $existingIds = $existingRoles->column('id');
        $invalidIds = array_diff($ids, $existingIds);
        
        if (!empty($invalidIds)) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在: ' . implode(',', $invalidIds));
        }
        
        // 检查是否有角色正在使用
        foreach ($existingRoles as $role) {
            if ($role->isUsed($role->id)) {
                throw new ApiException(Code::DATA_IN_USE, "角色 '{$role->name}' 正在使用中，无法删除");
            }
        }
        
        // 批量软删除
        $result = $roleModel->destroy($ids);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '批量删除角色失败');
        }
        
        return true;
    }

    /**
     * 验证角色数据
     * @param array $data 角色数据
     * @param bool $isCreate 是否为创建操作
     * @throws ApiException
     */
    private function validateRoleData(array $data, bool $isCreate = false): void
    {
        // 创建时必须提供角色代码和名称
        if ($isCreate) {
            if (empty($data['code'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '角色代码不能为空');
            }
            if (empty($data['name'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '角色名称不能为空');
            }
        }
        
        // 角色代码格式验证
        if (!empty($data['code'])) {
            if (!Role::validateCode($data['code'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '角色代码格式不正确，只能包含字母、数字、下划线，长度3-50个字符，且必须以字母开头');
            }
        }
        
        // 角色名称格式验证
        if (!empty($data['name'])) {
            if (!Role::validateName($data['name'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '角色名称长度必须在2-100个字符之间');
            }
        }
        
        // 描述长度验证
        if (!empty($data['description'])) {
            if (mb_strlen($data['description']) > 500) {
                throw new ApiException(Code::PARAMETER_ERROR, '角色描述长度不能超过500个字符');
            }
        }
        
        // 排序值验证
        if (isset($data['sort'])) {
            if (!is_numeric($data['sort']) || $data['sort'] < 0) {
                throw new ApiException(Code::PARAMETER_ERROR, '排序值必须为非负整数');
            }
        }
    }

    /**
     * 获取下一个排序值
     * @return int
     */
    private function getNextSort(): int
    {
        $roleModel = ModelFactory::role();
        $maxSort = $roleModel->max('sort');
        return ($maxSort ?? 0) + 1;
    }
}