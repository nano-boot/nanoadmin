<?php

namespace plugin\theadmin\app\service;

use Illuminate\Pagination\LengthAwarePaginator;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\Role;

/**
 * 角色服务类
 */
class RoleService
{
    /**
     * 模型
     */
    private Role $model;

    /**
     * 构造函数
     * @param Role $model
     */
    public function __construct(Role $model)
    {
        $this->model = $model;
    }
    /**
     * 获取角色列表
     * @param array $params 查询参数
     *  - page: 页码
     *  - limit: 每页数量
     *  - keyword: 关键词（name/code/description 模糊搜索）
     *  - name: 角色名称（模糊搜索）
     *  - code: 角色代码（模糊搜索）
     *  - status: 状态（0/1）
     * @return LengthAwarePaginator
     */
    public function getRoleList(array $params = []): LengthAwarePaginator
    {
        // 分页参数
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, (int)($params['limit'] ?? 15));

        // 查询参数
        $keyword = trim((string)($params['keyword'] ?? ''));
        $status = $params['status'] ?? '';
        $name = trim((string)($params['name'] ?? ''));
        $code = trim((string)($params['code'] ?? ''));

        $query = Role::query()
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->when($name !== '', function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%");
            })
            ->when($code !== '', function ($q) use ($code) {
                $q->where('code', 'like', "%{$code}%");
            })
            ->when($status !== '', function ($q) use ($status) {
                $q->where('status', (int)$status);
            })
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');

               // 格式化数据
      

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($role) {
            return $this->formatRoleRow($role);
        });

        return $paginator;
    }

    /**
     * 将角色模型格式化为数组行
     * @param Role $role
     * @return array
     */
    private function formatRoleRow($role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'description' => $role->description,
            'status' => $role->status,
            'sort' => $role->sort,
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at
        ];
    }

    /**
     * 根据ID获取角色详情
     * @param int $id 角色ID
     * @return Role
     * @throws ApiException
     */
    public function getRoleById(int $id): Role
    {
        $role = $this->model->with(['permissions', 'menus'])->find($id);
        
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
        // 设置排序值
        if (!isset($data['sort'])) {
            $data['sort'] = $this->getNextSort();
        }
        // 创建角色
        return $this->model->create($data);
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
        $role = $this->model->find($id);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        return $role->update($data);
    }

    /**
     * 删除角色（软删除）
     * @param int $id 角色ID
     * @return bool
     * @throws ApiException
     */
    public function deleteRole(int $id): bool
    {
        // 检查角色是否存在
        $role = $this->model->find($id);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 检查角色是否被使用
        $adminCount = $role->admins()->count();
        if ($adminCount > 0) {
            throw new ApiException(Code::DATA_IN_USE, '角色正在使用中，无法删除');
        }
        
        // 软删除
        $result = $this->model->destroy($id);
        
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
        // 检查角色是否存在
        $role = $this->model->find($id);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 更新状态
        $result = $this->model->where('id', $id)->update([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新角色状态失败');
        }
        
        return true;
    }

    /**
     * 为角色分配权限（接收菜单ID和权限标识）
     * @param int $roleId 角色ID
     * @param array $data { menuIds: int[], authMarks: string[] }
     * @return bool
     * @throws ApiException
     */
    public function assignPermissions(int $roleId, array $data): bool
    {
        if ($roleId === 1) {
            throw new ApiException(Code::FORBIDDEN, '系统默认角色不允许分配权限');
        }
        // 检查角色是否存在
        $role = $this->model->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        $menuIds = $data['menuIds'] ?? [];
        $authMarks = $data['authMarks'] ?? [];
        
        // 验证菜单是否存在
        if (!empty($menuIds)) {
            $menuModel = ModelFactory::menu();
            $existingMenuIds = $menuModel->whereIn('id', $menuIds)
                ->where('status', true)
                ->pluck('id')
                ->toArray();
            
            $invalidMenuIds = array_diff($menuIds, $existingMenuIds);
            if (!empty($invalidMenuIds)) {
                throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在: ' . implode(',', $invalidMenuIds));
            }
        }
        
        // 通过 authMark 查找权限ID
        $permissionIds = [];
        if (!empty($authMarks)) {
            $permissionModel = ModelFactory::permission();
            $permissions = $permissionModel->whereIn('mark', $authMarks)
                ->where('status', true)
                ->get();
            
            foreach ($permissions as $permission) {
                $permissionIds[] = $permission->id;
            }
            
            $foundMarks = $permissions->pluck('mark')->toArray();
            $invalidMarks = array_diff($authMarks, $foundMarks);
            if (!empty($invalidMarks)) {
                throw new ApiException(Code::PERMISSION_NOT_FOUND, '权限不存在: ' . implode(',', $invalidMarks));
            }
        }
        
        try {
            // 开始事务
            \support\Db::beginTransaction();
            
            // 分配菜单
            if (method_exists($role, 'assignMenus')) {
                $role->assignMenus($menuIds);
            }
            
            // 分配权限
            if (method_exists($role, 'assignPermissions')) {
                $role->assignPermissions($permissionIds);
            }
            
            // 提交事务
            \support\Db::commit();
            
            return true;
        } catch (\Exception $e) {
            // 回滚事务
            \support\Db::rollBack();
            throw new ApiException(Code::SYSTEM_ERROR, '分配权限失败: ' . $e->getMessage());
        }
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
        // 检查角色是否存在
        $role = $this->model->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 验证菜单是否存在
        if (!empty($menuIds)) {
            $menuModel = ModelFactory::menu();
            $existingMenus = $menuModel->whereIn('id', $menuIds)
                ->where('status', true)
                ->pluck('id')
                ->toArray();
            
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
     * 获取角色权限列表（返回ID和标识用于前端选中）
     * @param int $roleId 角色ID
     * @return array { menuIds: int[], authMarks: string[] }
     * @throws ApiException
     */
    public function getRolePermissions(int $roleId): array
    {
        // 检查角色是否存在
        $role = $this->model->with(['menus', 'permissions'])->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 收集菜单ID（不包含权限）
        $menuIds = [];
        foreach ($role->menus as $menu) {
            $menuIds[] = $menu->id;
        }
        
        // 收集权限标识（authMark格式：menu:action）
        $authMarks = [];
        foreach ($role->permissions as $permission) {
            if (!empty($permission->mark)) {
                $authMarks[] = $permission->mark;
            }
        }
        
        return [
            'menuIds' => array_unique(array_values($menuIds)),
            'authMarks' => array_unique(array_values($authMarks))
        ];
    }

    /**
     * 获取角色菜单列表
     * @param int $roleId 角色ID
     * @return array
     * @throws ApiException
     */
    public function getRoleMenus(int $roleId): array
    {
        // 检查角色是否存在
        $role = $this->model->find($roleId);
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
            $role = $this->model->find($roleId);
            
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
            $role = $this->model->find($roleId);
            
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
        // 检查源角色是否存在
        $sourceRole = $this->model->find($sourceRoleId);
        if (!$sourceRole) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '源角色不存在');
        }
        
        // 检查目标角色是否存在
        $targetRole = $this->model->find($targetRoleId);
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
        // 检查源角色是否存在
        $sourceRole = $this->model->find($sourceRoleId);
        if (!$sourceRole) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '源角色不存在');
        }
        
        // 检查目标角色是否存在
        $targetRole = $this->model->find($targetRoleId);
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
        // 检查角色是否存在
        $role = $this->model->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        return $role->getStats();
    }

    /**
     * 获取启用的角色列表用于下拉选择
     * @return array
     */
    public function getEnabledRoles(): array
    {
        return $this->model->getEnabledList()->select('id', 'name')->toArray();
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
        // 检查角色是否存在
        $role = $this->model->find($roleId);
        if (!$role) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在');
        }
        
        // 验证排序值
        if ($sort < 0) {
            throw new ApiException(Code::INVALID_SORT_ORDER, '排序值不能为负数');
        }
        
        // 更新排序
        $result = $this->model->where('id', $roleId)->update([
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
        
        // 检查角色是否存在
        $existingRoles = $this->model->whereIn('id', $ids)->get();
        $existingIds = $existingRoles->pluck('id')->toArray();
        $invalidIds = array_diff($ids, $existingIds);
        
        if (!empty($invalidIds)) {
            throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在: ' . implode(',', $invalidIds));
        }
        
        // 检查是否有角色正在使用
        /** @var Role $role */
        foreach ($existingRoles as $role) {
            // 检查是否有管理员使用此角色
            $adminCount = $role->admins()->count();
            if ($adminCount > 0) {
                throw new ApiException(Code::DATA_IN_USE, "角色 '{$role->name}' 正在使用中，无法删除");
            }
        }
        
        // 批量软删除
        $result = $this->model->destroy($ids);
        
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
        $maxSort = $this->model->max('sort');
        return ($maxSort ?? 0) + 1;
    }
}