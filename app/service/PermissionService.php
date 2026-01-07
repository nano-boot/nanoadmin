<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\Permission;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 权限服务类
 */
class PermissionService
{
    /**
     * 获取权限列表
     * @param array $params 查询参数
     * @return LengthAwarePaginator
     */
    public function getPermissionList(array $params = []): LengthAwarePaginator
    {
        $permissionModel = ModelFactory::permission();
        
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
        if (!empty($params['resource'])) {
            $searchParams['resource'] = $params['resource'];
        }
        if (!empty($params['action'])) {
            $searchParams['action'] = $params['action'];
        }
        
        $result = $permissionModel->getListWithRoleCount(array_merge($where, $searchParams), $page, $limit);

        // 创建LengthAwarePaginator实例
        return new LengthAwarePaginator(
            $result['list'],           // items
            $result['total'],          // total
            $result['page_size'],      // per page
            $result['page'],           // current page
            [                          // options
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * 根据ID获取权限详情
     * @param int $id 权限ID
     * @return Permission
     * @throws ApiException
     */
    public function getPermissionById(int $id): Permission
    {
        $permissionModel = ModelFactory::permission();
        $permission = $permissionModel->with('roles')->find($id);
        
        if (!$permission) {
            throw new ApiException(Code::PERMISSION_NOT_FOUND, '权限不存在');
        }
        
        return $permission;
    }

    /**
     * 创建权限
     * @param array $data 权限数据
     * @return Permission
     * @throws ApiException
     */
    public function createPermission(array $data): Permission
    {
        // 数据验证
        $this->validatePermissionData($data, true);
        
        $permissionModel = ModelFactory::permission();
        
        // 检查权限代码是否已存在
        if ($permissionModel->where('code', $data['code'])->find()) {
            throw new ApiException(Code::DUPLICATE_NAME, '权限代码已存在');
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
        
        // 创建权限
        $permission = $permissionModel->createPermission($data);
        
        if (!$permission) {
            throw new ApiException(Code::SYSTEM_ERROR, '创建权限失败');
        }
        
        return $permission;
    }

    /**
     * 更新权限
     * @param int $id 权限ID
     * @param array $data 更新数据
     * @return bool
     * @throws ApiException
     */
    public function updatePermission(int $id, array $data): bool
    {
        // 数据验证
        $this->validatePermissionData($data, false);
        
        $permissionModel = ModelFactory::permission();
        
        // 检查权限是否存在
        $permission = $permissionModel->find($id);
        if (!$permission) {
            throw new ApiException(Code::PERMISSION_NOT_FOUND, '权限不存在');
        }
        
        // 检查权限代码是否已被其他权限使用
        if (!empty($data['code'])) {
            $existingPermission = $permissionModel->where('code', $data['code'])
                
                ->where('id', '<>', $id)
                ->find();
            if ($existingPermission) {
                throw new ApiException(Code::DUPLICATE_NAME, '权限代码已存在');
            }
        }
        
        // 更新时间
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // 更新权限
        $result = $permissionModel->updatePermission($id, $data);
        
        if (!$result) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新权限失败');
        }
        
        return true;
    }

    /**
     * 删除权限（软删除）
     * @param int $id 权限ID
     * @return bool
     * @throws ApiException
     */
    public function deletePermission(int $id): bool
    {
        $permissionModel = ModelFactory::permission();
        
        // 检查权限是否存在
        $permission = $permissionModel->find($id);
        if (!$permission) {
            throw new ApiException(Code::PERMISSION_NOT_FOUND, '权限不存在');
        }
        
        // 检查权限是否被使用
        if ($permission->isUsed($id)) {
            throw new ApiException(Code::DATA_IN_USE, '权限正在使用中，无法删除');
        }
        
        // 软删除
        $result = $permissionModel->destroy($id);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '删除权限失败');
        }
        
        return true;
    }

    /**
     * 启用/禁用权限
     * @param int $id 权限ID
     * @param bool $status 状态
     * @return bool
     * @throws ApiException
     */
    public function togglePermissionStatus(int $id, bool $status): bool
    {
        $permissionModel = ModelFactory::permission();
        
        // 检查权限是否存在
        $permission = $permissionModel->find($id);
        if (!$permission) {
            throw new ApiException(Code::PERMISSION_NOT_FOUND, '权限不存在');
        }
        
        // 更新状态
        $result = $permissionModel->where('id', $id)->update([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新权限状态失败');
        }
        
        return true;
    }

    /**
     * 获取权限树形结构（按资源分组）
     * @return array
     */
    public function getPermissionTree(): array
    {
        $permissionModel = ModelFactory::permission();
        return $permissionModel->getPermissionTree();
    }

    /**
     * 获取所有资源类型
     * @return array
     */
    public function getAllResources(): array
    {
        $permissionModel = ModelFactory::permission();
        return $permissionModel->getAllResources();
    }

    /**
     * 获取所有操作类型
     * @return array
     */
    public function getAllActions(): array
    {
        $permissionModel = ModelFactory::permission();
        return $permissionModel->getAllActions();
    }

    /**
     * 根据资源类型获取权限列表
     * @param string $resource 资源类型
     * @return array
     */
    public function getPermissionsByResource(string $resource): array
    {
        $permissionModel = ModelFactory::permission();
        $permissions = $permissionModel->getByResource($resource);
        
        $result = [];
        foreach ($permissions as $permission) {
            $result[] = [
                'id' => $permission->id,
                'code' => $permission->code,
                'name' => $permission->name,
                'resource' => $permission->resource,
                'action' => $permission->action,
                'description' => $permission->description
            ];
        }
        
        return $result;
    }

    /**
     * 根据操作类型获取权限列表
     * @param string $action 操作类型
     * @return array
     */
    public function getPermissionsByAction(string $action): array
    {
        $permissionModel = ModelFactory::permission();
        $permissions = $permissionModel->getByAction($action);
        
        $result = [];
        foreach ($permissions as $permission) {
            $result[] = [
                'id' => $permission->id,
                'code' => $permission->code,
                'name' => $permission->name,
                'resource' => $permission->resource,
                'action' => $permission->action,
                'description' => $permission->description
            ];
        }
        
        return $result;
    }

    /**
     * 批量检查权限
     * @param array $permissions 权限代码数组
     * @param int $adminId 管理员ID（可选）
     * @return array
     */
    public function batchCheckPermissions(array $permissions, int $adminId = 0): array
    {
        $permissionModel = ModelFactory::permission();
        return $permissionModel->batchCheck($permissions, $adminId);
    }

    /**
     * 获取启用的权限列表（用于下拉选择）
     * @return array
     */
    public function getEnabledPermissions(): array
    {
        $permissionModel = ModelFactory::permission();
        $permissions = $permissionModel->getEnabledList();
        
        $result = [];
        foreach ($permissions as $permission) {
            $result[] = [
                'id' => $permission->id,
                'code' => $permission->code,
                'name' => $permission->name,
                'resource' => $permission->resource,
                'action' => $permission->action,
                'description' => $permission->description,
                'sort' => $permission->sort
            ];
        }
        
        return $result;
    }

    /**
     * 根据权限代码获取权限信息
     * @param string $code 权限代码
     * @return Permission|null
     */
    public function getPermissionByCode(string $code): ?Permission
    {
        $permissionModel = ModelFactory::permission();
        return $permissionModel->getByCode($code);
    }

    /**
     * 生成RESTful权限
     * @param string $resource 资源名称
     * @param array $actions 操作列表，默认为标准RESTful操作
     * @return array 返回创建的权限列表
     * @throws ApiException
     */
    public function generateRestfulPermissions(string $resource, array $actions = []): array
    {
        if (empty($actions)) {
            $actions = ['index', 'show', 'create', 'store', 'edit', 'update', 'destroy'];
        }
        
        $actionNames = [
            'index' => '列表',
            'show' => '详情',
            'create' => '创建页面',
            'store' => '创建',
            'edit' => '编辑页面',
            'update' => '更新',
            'destroy' => '删除'
        ];
        
        $createdPermissions = [];
        
        foreach ($actions as $action) {
            $code = Permission::generateRestfulCode($resource, $action);
            $name = ($actionNames[$action] ?? $action) . $resource;
            
            try {
                $permission = $this->createPermission([
                    'code' => $code,
                    'name' => $name,
                    'resource' => $resource,
                    'action' => $action,
                    'description' => "对{$resource}资源进行{$actionNames[$action]}操作的权限"
                ]);
                
                $createdPermissions[] = $permission;
            } catch (ApiException $e) {
                // 如果权限已存在，跳过
                if ($e->getErrorCode() !== Code::DUPLICATE_NAME->value) {
                    throw $e;
                }
            }
        }
        
        return $createdPermissions;
    }

    /**
     * 调整权限排序
     * @param int $permissionId 权限ID
     * @param int $sort 排序值
     * @return bool
     * @throws ApiException
     */
    public function updatePermissionSort(int $permissionId, int $sort): bool
    {
        $permissionModel = ModelFactory::permission();
        
        // 检查权限是否存在
        $permission = $permissionModel->find($permissionId);
        if (!$permission) {
            throw new ApiException(Code::PERMISSION_NOT_FOUND, '权限不存在');
        }
        
        // 验证排序值
        if ($sort < 0) {
            throw new ApiException(Code::INVALID_SORT_ORDER, '排序值不能为负数');
        }
        
        // 更新排序
        $result = $permissionModel->where('id', $permissionId)->update([
            'sort' => $sort,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新权限排序失败');
        }
        
        return true;
    }

    /**
     * 批量删除权限
     * @param array $ids 权限ID数组
     * @return bool
     * @throws ApiException
     */
    public function batchDeletePermissions(array $ids): bool
    {
        if (empty($ids)) {
            throw new ApiException(Code::PARAMETER_ERROR, '请选择要删除的权限');
        }
        
        $permissionModel = ModelFactory::permission();
        
        // 检查权限是否存在
        $existingPermissions = $permissionModel->whereIn('id', $ids)->get();
        $existingIds = $existingPermissions->pluck('id')->toArray();
        $invalidIds = array_diff($ids, $existingIds);
        
        if (!empty($invalidIds)) {
            throw new ApiException(Code::PERMISSION_NOT_FOUND, '权限不存在: ' . implode(',', $invalidIds));
        }
        
        // 检查是否有权限正在使用
        foreach ($existingPermissions as $permission) {
            if ($permission->isUsed($permission->id)) {
                throw new ApiException(Code::DATA_IN_USE, "权限 '{$permission->name}' 正在使用中，无法删除");
            }
        }
        
        // 批量软删除
        $result = $permissionModel->destroy($ids);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '批量删除权限失败');
        }
        
        return true;
    }

    /**
     * 验证权限数据
     * @param array $data 权限数据
     * @param bool $isCreate 是否为创建操作
     * @throws ApiException
     */
    private function validatePermissionData(array $data, bool $isCreate = false): void
    {
        // 创建时必须提供权限代码和名称
        if ($isCreate) {
            if (empty($data['code'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '权限代码不能为空');
            }
            if (empty($data['name'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '权限名称不能为空');
            }
            if (empty($data['resource'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '资源类型不能为空');
            }
            if (empty($data['action'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '操作类型不能为空');
            }
        }
        
        // 权限代码格式验证
        if (!empty($data['code'])) {
            if (!Permission::validateCode($data['code'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '权限代码格式不正确，格式应为：resource:action 或 resource:action:detail');
            }
        }
        
        // 权限名称格式验证
        if (!empty($data['name'])) {
            if (!Permission::validateName($data['name'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '权限名称长度必须在2-100个字符之间');
            }
        }
        
        // 资源类型格式验证
        if (!empty($data['resource'])) {
            if (!Permission::validateResource($data['resource'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '资源类型格式不正确，只能包含字母、数字、下划线，长度2-50个字符，且必须以字母开头');
            }
        }
        
        // 操作类型格式验证
        if (!empty($data['action'])) {
            if (!Permission::validateAction($data['action'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '操作类型格式不正确，只能包含字母、数字、下划线，长度2-50个字符，且必须以字母开头');
            }
        }
        
        // 描述长度验证
        if (!empty($data['description'])) {
            if (mb_strlen($data['description']) > 500) {
                throw new ApiException(Code::PARAMETER_ERROR, '权限描述长度不能超过500个字符');
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
        $permissionModel = ModelFactory::permission();
        $maxSort = $permissionModel->max('sort');
        return ($maxSort ?? 0) + 1;
    }
}