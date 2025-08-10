<?php

namespace plugin\theadmin\app\model;

use think\Collection;
use think\db\exception\DbException;
use think\model\relation\BelongsToMany;
use think\Paginator;

/**
 * 权限模型
 * @property mixed $roles
 */
class Permission extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $name = 'sys_permission';

    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 字段类型转换
     * @var array
     */
    protected array $type = [
        'id' => 'integer',
        'status' => 'boolean',
        'sort' => 'integer',
        'deleted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 关联角色
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'sys_role_permission', 'permission_id', 'role_id');
    }

    /**
     * 获取权限列表（带角色数量）
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return Paginator
     * @throws DbException
     */
    public function getListWithRoleCount(array $where = [], int $page = 1, int $limit = 15): Paginator
    {
        $query = $this->where($where);
        
        // 支持权限名称搜索
        if (!empty($where['name'])) {
            $query->where('name', 'like', '%' . $where['name'] . '%');
        }
        
        // 支持权限代码搜索
        if (!empty($where['code'])) {
            $query->where('code', 'like', '%' . $where['code'] . '%');
        }
        
        // 支持资源类型筛选
        if (!empty($where['resource'])) {
            $query->where('resource', $where['resource']);
        }
        
        // 支持操作类型筛选
        if (!empty($where['action'])) {
            $query->where('action', $where['action']);
        }
        
        return $query->order('sort asc, id desc')->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
    }

    /**
     * 创建权限
     * @param array $data 权限数据
     * @return static|false
     */
    public function createPermission(array $data): Permission|bool|static
    {
        // 检查权限代码是否已存在
        if ($this->checkExists(['code' => $data['code']])) {
            return false;
        }
        
        // 设置默认排序值
        if (!isset($data['sort'])) {
            $data['sort'] = $this->getNextSort();
        }
        
        return $this->save($data) ? $this : false;
    }

    /**
     * 更新权限
     * @param int $id 权限ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updatePermission(int $id, array $data): bool
    {
        // 检查权限代码是否已存在（排除自己）
        if (isset($data['code']) && $this->checkExists(['code' => $data['code']], $id)) {
            return false;
        }
        
        return $this->where('id', $id)->update($data) !== false;
    }

    /**
     * 检查权限是否被角色使用
     * @param int $id 权限ID
     * @return bool
     */
    public function isUsed(int $id): bool
    {
        // 检查是否有角色使用此权限
        $roleCount = $this->roles()->where('permission_id', $id)->count();
        
        return $roleCount > 0;
    }

    /**
     * 验证权限代码格式
     * @param string $code 权限代码
     * @return bool
     */
    public static function validateCode(string $code): bool
    {
        // 权限代码格式：resource:action 或 resource:action:detail
        // 例如：user:create, user:update, user:delete, user:view:profile
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*:[a-zA-Z][a-zA-Z0-9_]*(?::[a-zA-Z][a-zA-Z0-9_]*)?$/', $code);
    }

    /**
     * 验证权限名称格式
     * @param string $name 权限名称
     * @return bool
     */
    public static function validateName(string $name): bool
    {
        // 权限名称长度2-100，不能为空
        return !empty(trim($name)) && mb_strlen(trim($name)) >= 2 && mb_strlen(trim($name)) <= 100;
    }

    /**
     * 验证资源类型格式
     * @param string $resource 资源类型
     * @return bool
     */
    public static function validateResource(string $resource): bool
    {
        // 资源类型只能包含字母、数字、下划线，长度2-50
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,49}$/', $resource);
    }

    /**
     * 验证操作类型格式
     * @param string $action 操作类型
     * @return bool
     */
    public static function validateAction(string $action): bool
    {
        // 操作类型只能包含字母、数字、下划线，长度2-50
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,49}$/', $action);
    }

    /**
     * 检查权限是否匹配指定的资源和操作
     * @param string $resource 资源类型
     * @param string $action 操作类型
     * @return bool
     */
    public function matches(string $resource, string $action): bool
    {
        return $this->resource === $resource && $this->action === $action;
    }

    /**
     * 检查权限是否激活
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status == 1 && !$this->deleted;
    }

    /**
     * 获取权限的角色列表
     * @return array
     */
    public function getRoleList(): array
    {
        $roles = $this->roles;
        $list = [];
        
        foreach ($roles as $role) {
            $list[] = [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name
            ];
        }
        
        return $list;
    }

    /**
     * 获取权限统计信息
     * @return array
     */
    public function getStats(): array
    {
        return [
            'role_count' => $this->roles()->count(),
            'is_active' => $this->isActive()
        ];
    }

    /**
     * 根据资源类型获取权限列表
     * @param string $resource 资源类型
     * @return Collection
     */
    public function getByResource(string $resource): Collection
    {
        return $this->where('resource', $resource)
                   ->enabled()
                   ->order('sort asc, id desc')
                   ->select();
    }

    /**
     * 根据操作类型获取权限列表
     * @param string $action 操作类型
     * @return Collection
     */
    public function getByAction(string $action): Collection
    {
        return $this->where('action', $action)
                   ->enabled()
                   ->order('sort asc, id desc')
                   ->select();
    }

    /**
     * 获取所有资源类型
     * @return array
     */
    public function getAllResources(): array
    {
        return $this->enabled()
                   ->group('resource')
                   ->column('resource');
    }

    /**
     * 获取所有操作类型
     * @return array
     */
    public function getAllActions(): array
    {
        return $this->enabled()
                   ->group('action')
                   ->column('action');
    }

    /**
     * 获取权限树形结构（按资源分组）
     * @return array
     */
    public function getPermissionTree(): array
    {
        $permissions = $this->enabled()->order('sort asc, id desc')->select();
        $tree = [];
        
        foreach ($permissions as $permission) {
            $resource = $permission->resource;
            
            if (!isset($tree[$resource])) {
                $tree[$resource] = [
                    'resource' => $resource,
                    'label' => $resource,
                    'children' => []
                ];
            }
            
            $tree[$resource]['children'][] = [
                'id' => $permission->id,
                'code' => $permission->code,
                'name' => $permission->name,
                'resource' => $permission->resource,
                'action' => $permission->action,
                'description' => $permission->description
            ];
        }
        
        return array_values($tree);
    }

    /**
     * 批量检查权限
     * @param array $permissions 权限代码数组
     * @param int $adminId 管理员ID（可选）
     * @return array 返回权限检查结果
     */
    public function batchCheck(array $permissions, int $adminId = 0): array
    {
        $result = [];
        
        foreach ($permissions as $permissionCode) {
            $result[$permissionCode] = false;
            
            // 检查权限是否存在且启用
            $permission = $this->where('code', $permissionCode)->enabled()->find();
            if (!$permission) {
                continue;
            }
            
            if ($adminId > 0) {
                // 检查指定管理员是否有此权限
                $admin = Admin::find($adminId);
                if ($admin && $admin->hasPermission($permissionCode)) {
                    $result[$permissionCode] = true;
                }
            } else {
                // 只检查权限是否存在
                $result[$permissionCode] = true;
            }
        }
        
        return $result;
    }

    /**
     * 获取启用的权限列表（用于下拉选择）
     * @return Collection
     */
    public function getEnabledList(): Collection
    {
        return $this->enabled()->order('sort asc, id desc')->select();
    }

    /**
     * 根据权限代码获取权限信息
     * @param string $code 权限代码
     * @return static|null
     */
    public function getByCode(string $code): ?Permission
    {
        return $this->where('code', $code)->find();
    }

    /**
     * 检查权限代码是否符合RESTful规范
     * @param string $code 权限代码
     * @return bool
     */
    public static function isRestfulCode(string $code): bool
    {
        $restfulActions = ['index', 'show', 'create', 'store', 'edit', 'update', 'destroy'];
        $parts = explode(':', $code);
        
        if (count($parts) < 2) {
            return false;
        }
        
        return in_array($parts[1], $restfulActions);
    }

    /**
     * 生成RESTful权限代码
     * @param string $resource 资源名称
     * @param string $action 操作名称
     * @return string
     */
    public static function generateRestfulCode(string $resource, string $action): string
    {
        return strtolower($resource) . ':' . strtolower($action);
    }
}