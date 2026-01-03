<?php

namespace plugin\theadmin\app\model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;

/**
 * 角色模型
 */
class Role extends BaseModel
{
   
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_role';

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
        'name', 'code', 'description', 'status', 'sort'
    ];

    protected $casts = [
        'created_at' => 'string',
        'updated_at' => 'string',
    ];

    protected static array $searchLikeFields = ['name','code'];
    protected static array $searchEqualFields = ['status'];
    protected static array $searchKeywordFields = ['name'];
    protected static array $searchRangeFields = ['created_at']; 

    protected static function booted(): void
    {
        static::updating(function (Role $role) {
            if ($role->id === 1) {
                if ($role->isDirty('status')) {
                    $role->syncOriginalAttribute('status');
                }
            }
        });
        static::deleting(function (Role $role) {
            if ($role->id === 1) {
                throw new ApiException(Code::FORBIDDEN, '系统默认角色不允许删除');
            }
        });
    }
    /**
     * 关联管理员
     * @return BelongsToMany
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'sys_admin_role', 'role_id', 'admin_id');
    }

    /**
     * 关联权限
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'sys_role_permission', 'role_id', 'permission_id');
    }

    /**
     * 关联菜单
     * @return BelongsToMany
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'sys_role_menu', 'role_id', 'menu_id');
    }


    /**
     * 获取角色列表（带权限和菜单数量）
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function getListWithCounts(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->where($where);
        
        // 支持角色名称搜索
        if (!empty($where['name'])) {
            $query->where('name', 'like', '%' . $where['name'] . '%');
        }
        
        // 支持角色代码搜索
        if (!empty($where['code'])) {
            $query->where('code', 'like', '%' . $where['code'] . '%');
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
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 创建角色
     * @param array $data 角色数据
     * @return static|false
     */
    public function createRole(array $data): Role|bool
    {
        // 检查角色代码是否已存在
        if ($this->where('code', $data['code'])->exists()) {
            return false;
        }
        
        // 设置默认排序值
        if (!isset($data['sort'])) {
            $data['sort'] = $this->getNextSort();
        }
        
        return $this->create($data);
    }

    /**
     * 更新角色
     * @param int $id 角色ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateRole(int $id, array $data): bool
    {
        // 检查角色代码是否已存在（排除自己）
        if (isset($data['code']) && $this->where('code', $data['code'])->where('id', '!=', $id)->exists()) {
            return false;
        }
        
        return $this->where('id', $id)->update($data);
    }

    /**
     * 分配权限
     * @param array $permissionIds 权限ID数组
     * @return bool
     */
    public function assignPermissions(array $permissionIds): bool
    {
        // 先删除现有权限关联
        $this->permissions()->detach();
        
        // 添加新的权限关联
        if (!empty($permissionIds)) {
            $this->permissions()->attach($permissionIds);
        }
        
        return true;
    }

    /**
     * 分配菜单
     * @param array $menuIds 菜单ID数组
     * @return bool
     */
    public function assignMenus(array $menuIds): bool
    {
        // 先删除现有菜单关联
        $this->menus()->detach();
        
        // 添加新的菜单关联
        if (!empty($menuIds)) {
            $this->menus()->attach($menuIds);
        }
        
        return true;
    }

    /**
     * 检查角色是否被使用
     * @param int $id 角色ID
     * @return bool
     */
    public function isUsed(int $id): bool
    {
        // 检查是否有管理员使用此角色
        $adminCount = $this->admins()->where('role_id', $id)->count();
        
        return $adminCount > 0;
    }

    /**
     * 获取角色的权限代码列表
     * @return array
     */
    public function getPermissionCodes(): array
    {
        $permissions = $this->permissions()->get();
        $codes = [];
        
        foreach ($permissions as $permission) {
            $codes[] = $permission->code;
        }
        
        return $codes;
    }

    /**
     * 获取角色的菜单ID列表
     * @return array
     */
    public function getMenuIds(): array
    {
        $menus = $this->menus()->get();
        $ids = [];
        
        foreach ($menus as $menu) {
            $ids[] = $menu->id;
        }
        
        return $ids;
    }

    /**
     * 检查是否有指定权限
     * @param string $permission 权限代码
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions()->get();
        
        foreach ($permissions as $perm) {
            if ($perm->code === $permission) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 检查是否有指定菜单权限
     * @param int $menuId 菜单ID
     * @return bool
     */
    public function hasMenu(int $menuId): bool
    {
        $menus = $this->menus()->get();
        
        foreach ($menus as $menu) {
            if ($menu->id === $menuId) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 验证角色状态
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status == 1 && !$this->deleted;
    }

    /**
     * 验证角色代码格式
     * @param string $code 角色代码
     * @return bool
     */
    public static function validateCode(string $code): bool
    {
        // 角色代码只能包含字母、数字、下划线，长度3-50
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,49}$/', $code);
    }

    /**
     * 验证角色名称格式
     * @param string $name 角色名称
     * @return bool
     */
    public static function validateName(string $name): bool
    {
        // 角色名称长度2-100，不能为空
        return !empty(trim($name)) && mb_strlen(trim($name)) >= 2 && mb_strlen(trim($name)) <= 100;
    }

    /**
     * 获取角色的所有权限（包括继承的权限）
     * @return array
     */
    public function getAllPermissions(): array
    {
        $permissions = [];
        
        // 获取直接分配的权限
        $directPermissions = $this->permissions()->get();
        foreach ($directPermissions as $permission) {
            $permissions[$permission->code] = $permission;
        }
        
        // 这里可以扩展权限继承逻辑，比如从父角色继承权限
        // 目前的设计中没有角色层级，所以暂时只返回直接权限
        
        return array_values($permissions);
    }

    /**
     * 获取角色的所有菜单（包括继承的菜单）
     * @return array
     */
    public function getAllMenus(): array
    {
        $menus = [];
        
        // 获取直接分配的菜单
        $directMenus = $this->menus()->get();
        foreach ($directMenus as $menu) {
            $menus[$menu->id] = $menu;
        }
        
        // 这里可以扩展菜单继承逻辑
        // 目前的设计中没有角色层级，所以暂时只返回直接菜单
        
        return array_values($menus);
    }

    /**
     * 检查角色权限是否足够执行指定操作
     * @param string $resource 资源类型
     * @param string $action 操作类型
     * @return bool
     */
    public function canPerform(string $resource, string $action): bool
    {
        $permissions = $this->getAllPermissions();
        
        foreach ($permissions as $permission) {
            if ($permission->resource === $resource && $permission->action === $action) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 获取角色统计信息
     * @return array
     */
    public function getStats(): array
    {
        return [
            'admin_count' => $this->admins()->count(),
            'permission_count' => $this->permissions()->count(),
            'menu_count' => $this->menus()->count(),
            'is_active' => $this->isActive()
        ];
    }

    /**
     * 复制角色权限到另一个角色
     * @param int $targetRoleId 目标角色ID
     * @return bool
     */
    public function copyPermissionsTo(int $targetRoleId): bool
    {
        $targetRole = self::find($targetRoleId);
        if (!$targetRole) {
            return false;
        }
        
        // 获取当前角色的权限ID
        $permissionIds = $this->permissions()->pluck('id')->toArray();
        
        // 复制权限到目标角色
        return $targetRole->assignPermissions($permissionIds);
    }

    /**
     * 复制角色菜单到另一个角色
     * @param int $targetRoleId 目标角色ID
     * @return bool
     */
    public function copyMenusTo(int $targetRoleId): bool
    {
        $targetRole = self::find($targetRoleId);
        if (!$targetRole) {
            return false;
        }
        
        // 获取当前角色的菜单ID
        $menuIds = $this->menus()->pluck('id')->toArray();
        
        // 复制菜单到目标角色
        return $targetRole->assignMenus($menuIds);
    }

    /**
     * 获取启用的角色列表（用于下拉选择）
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEnabledList()
    {
        return $this->where('status', 1)->orderBy('sort', 'asc')->orderBy('id', 'desc')->get();
    }

    /**
     * 获取下一个排序值
     * @param array $where 查询条件
     * @return int
     */
    public function getNextSort(array $where = []): int
    {
        $maxSort = $this->where($where)->max('sort');
        return $maxSort ? $maxSort + 10 : 100;
    }
}