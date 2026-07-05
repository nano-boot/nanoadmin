<?php

namespace plugin\nanoadmin\app\model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 权限模型
 * @property int $id
 * @property string $name
     * @property string $code
 * @property string $resource
 * @property string $action
 * @property string $description
 * @property int $status
 * @property int $sort
 * @property bool $deleted
 * @property string $created_at
 * @property string $updated_at
 */
class Permission extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_permission';

    /**
     * 主键
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的属性
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'resource',
        'action',
        'description',
        'status',
        'sort'
    ];

    /**
     * 字段类型转换
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'deleted' => 'boolean',
        'created_at' => 'string',
        'updated_at' => 'string'
    ];

    /**
     * 关联角色（多对多）
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
     * @return array
     */
    public function getListWithRoleCount(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->newQuery();

        // 支持权限名称搜索
        if (!empty($where['name'])) {
            $query->where('name', 'like', '%' . $where['name'] . '%');
        }

        // 支持权限编码搜索
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

        // 支持状态筛选
        if (isset($where['status'])) {
            $query->where('status', $where['status']);
        }

        $total = $query->count();
        $list = $query->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $limit,
            'last_page' => (int)ceil($total / $limit),
            'has_more' => $page * $limit < $total
        ];
    }

    /**
     * 创建权限
     * @param array $data 权限数据
     * @return static|false
     */
    public function createPermission(array $data): Permission|bool
    {
        // 检查权限编码是否已存在
        if ($this->where('code', $data['code'])->exists()) {
            return false;
        }

        // 设置默认排序值
        if (!isset($data['sort'])) {
            $data['sort'] = $this->getNextSort([]);
        }

        return $this->create($data);
    }

    /**
     * 更新权限
     * @param int $id 权限ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updatePermission(int $id, array $data): bool
    {
        // 检查权限编码是否已存在（排除自己）
        if (isset($data['code'])) {
            $exists = $this->where('code', $data['code'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return false;
            }
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
        $permission = $this->find($id);
        if (!$permission) {
            return false;
        }

        return $permission->roles()->count() > 0;
    }

    /**
     * 验证权限编码格式
     * @param string $code 权限编码
     * @return bool
     */
    public static function validateCode(string $code): bool
    {
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
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByResource(string $resource)
    {
        return $this->where('resource', $resource)
            ->where('status', 1)
            ->where('deleted', false)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * 根据操作类型获取权限列表
     * @param string $action 操作类型
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByAction(string $action)
    {
        return $this->where('action', $action)
            ->where('status', 1)
            ->where('deleted', false)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * 获取所有资源类型
     * @return array
     */
    public function getAllResources(): array
    {
        return $this->where('status', 1)
            ->where('deleted', false)
            ->groupBy('resource')
            ->pluck('resource')
            ->toArray();
    }

    /**
     * 获取所有操作类型
     * @return array
     */
    public function getAllActions(): array
    {
        return $this->where('status', 1)
            ->where('deleted', false)
            ->groupBy('action')
            ->pluck('action')
            ->toArray();
    }

    /**
     * 获取权限树形结构（按资源分组）
     * @return array
     */
    public function getPermissionTree(): array
    {
        $permissions = $this->where('status', 1)
            ->where('deleted', false)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->get();

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
     * @param array $codes 权限编码数组
     * @param int $adminId 管理员ID（可选）
     * @return array 返回权限检查结果
     */
    public function batchCheck(array $codes, int $adminId = 0): array
    {
        $result = [];

        foreach ($codes as $code) {
            $result[$code] = false;

            $permission = $this->where('code', $code)
                ->where('status', 1)
                ->where('deleted', false)
                ->first();

            if (!$permission) {
                continue;
            }

            if ($adminId > 0) {
                $admin = Admin::find($adminId);
                if ($admin && $admin->hasPermission($code)) {
                    $result[$code] = true;
                }
            } else {
                $result[$code] = true;
            }
        }

        return $result;
    }

    /**
     * 根据权限编码获取权限信息
     * @param string $code 权限编码
     * @return static|null
     */
    public function getByCode(string $code): ?Permission
    {
        return $this->where('code', $code)->first();
    }

    /**
     * 检查权限编码是否符合RESTful规范
     * @param string $code 权限编码
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
     * 生成RESTful权限编码
     * @param string $resource 资源名称
     * @param string $action 操作名称
     * @return string
     */
    public static function generateRestfulCode(string $resource, string $action): string
    {
        return strtolower($resource) . ':' . strtolower($action);
    }

}
