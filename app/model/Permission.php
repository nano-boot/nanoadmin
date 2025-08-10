<?php

namespace plugin\theadmin\app\model;

use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\model\relation\BelongsToMany;
use think\Paginator;

/**
 * 权限模型
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
        return $this->belongsToMany(Role::class, 'sys_role_permission', 'role_id', 'permission_id');
    }

    /**
     * 获取权限列表（支持分组）
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return Paginator
     * @throws DbException
     */
    public function getListGrouped(array $where = [], int $page = 1, int $limit = 15): Paginator
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
        
        return $query->order('resource asc, sort asc, id desc')->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
    }

    /**
     * 创建权限
     * @param array $data 权限数据
     * @return static|false
     */
    public function createPermission(array $data): bool|Permission|static
    {
        // 检查权限代码是否已存在
        if ($this->checkExists(['code' => $data['code']])) {
            return false;
        }
        
        // 设置默认排序值
        if (!isset($data['sort'])) {
            $data['sort'] = $this->getNextSort(['resource' => $data['resource']]);
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
     * 检查权限是否被使用
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
     * 获取权限树形结构（按资源分组）
     * @return array
     */
    public function getTree(): array
    {
        $permissions = $this->enabled()->order('resource asc, sort asc')->select();
        $tree = [];
        
        foreach ($permissions as $permission) {
            $resource = $permission->resource;
            
            if (!isset($tree[$resource])) {
                $tree[$resource] = [
                    'resource' => $resource,
                    'label' => $this->getResourceLabel($resource),
                    'children' => []
                ];
            }
            
            $tree[$resource]['children'][] = [
                'id' => $permission->id,
                'code' => $permission->code,
                'name' => $permission->name,
                'action' => $permission->action,
                'description' => $permission->description
            ];
        }
        
        return array_values($tree);
    }

    /**
     * 获取资源类型标签
     * @param string $resource 资源类型
     * @return string
     */
    private function getResourceLabel(string $resource): string
    {
        $labels = [
            'admin' => '管理员管理',
            'role' => '角色管理',
            'permission' => '权限管理',
            'menu' => '菜单管理',
            'system' => '系统管理'
        ];
        
        return $labels[$resource] ?? $resource;
    }

    /**
     * 获取启用的权限列表（用于角色分配）
     * @return Collection
     */
    public function getEnabledList(): Collection
    {
        return $this->enabled()->order('resource asc, sort asc')->select();
    }

    /**
     * 根据资源和操作获取权限
     * @param string $resource 资源类型
     * @param string $action 操作类型
     * @return static|null
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function getByResourceAction(string $resource, string $action): static
    {
        return $this->where([
            'resource' => $resource,
            'action' => $action,
            'status' => 1
        ])->find();
    }

    /**
     * 批量创建权限
     * @param array $permissions 权限数据数组
     * @return bool
     */
    public function batchCreate(array $permissions): bool
    {
        if (empty($permissions)) {
            return false;
        }
        
        $this->startTrans();
        try {
            foreach ($permissions as $permission) {
                // 检查是否已存在
                if (!$this->checkExists(['code' => $permission['code']])) {
                    $this->create($permission);
                }
            }
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * 获取资源类型列表
     * @return array
     */
    public function getResourceTypes(): array
    {
        $resources = $this->distinct(true)->column('resource');
        $types = [];
        
        foreach ($resources as $resource) {
            $types[] = [
                'value' => $resource,
                'label' => $this->getResourceLabel($resource)
            ];
        }
        
        return $types;
    }

    /**
     * 获取操作类型列表
     * @param string $resource 资源类型
     * @return array
     */
    public function getActionTypes(string $resource = ''): array
    {
        $query = $this->distinct(true);
        
        if ($resource) {
            $query->where('resource', $resource);
        }
        
        $actions = $query->column('action');
        $types = [];
        
        foreach ($actions as $action) {
            $types[] = [
                'value' => $action,
                'label' => $this->getActionLabel($action)
            ];
        }
        
        return $types;
    }

    /**
     * 获取操作类型标签
     * @param string $action 操作类型
     * @return string
     */
    private function getActionLabel(string $action): string
    {
        $labels = [
            'list' => '查看列表',
            'create' => '创建',
            'update' => '更新',
            'delete' => '删除',
            'view' => '查看详情',
            'export' => '导出',
            'import' => '导入'
        ];
        
        return $labels[$action] ?? $action;
    }
}