<?php

namespace plugin\theadmin\app\model;

/**
 * 角色模型
 */
class Role extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $name = 'sys_role';

    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 字段类型转换
     * @var array
     */
    protected $type = [
        'id' => 'integer',
        'status' => 'boolean',
        'sort' => 'integer',
        'deleted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 关联管理员
     * @return \think\model\relation\BelongsToMany
     */
    public function admins()
    {
        return $this->belongsToMany(Admin::class, 'sys_admin_role', 'admin_id', 'role_id');
    }

    /**
     * 关联权限
     * @return \think\model\relation\BelongsToMany
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'sys_role_permission', 'permission_id', 'role_id');
    }

    /**
     * 关联菜单
     * @return \think\model\relation\BelongsToMany
     */
    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'sys_role_menu', 'menu_id', 'role_id');
    }

    /**
     * 获取角色列表（带权限和菜单数量）
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return \think\Paginator
     */
    public function getListWithCounts($where = [], $page = 1, $limit = 15)
    {
        $query = $this->where($where);
        
        // 支持角色名称搜索
        if (isset($where['name']) && !empty($where['name'])) {
            unset($where['name']);
            $query->where('name', 'like', '%' . $where['name'] . '%');
        }
        
        // 支持角色代码搜索
        if (isset($where['code']) && !empty($where['code'])) {
            unset($where['code']);
            $query->where('code', 'like', '%' . $where['code'] . '%');
        }
        
        return $query->order('sort asc, id desc')->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
    }

    /**
     * 创建角色
     * @param array $data 角色数据
     * @return static|false
     */
    public function createRole($data)
    {
        // 检查角色代码是否已存在
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
     * 更新角色
     * @param int $id 角色ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateRole($id, $data)
    {
        // 检查角色代码是否已存在（排除自己）
        if (isset($data['code']) && $this->checkExists(['code' => $data['code']], $id)) {
            return false;
        }
        
        return $this->where('id', $id)->update($data) !== false;
    }

    /**
     * 分配权限
     * @param array $permissionIds 权限ID数组
     * @return bool
     */
    public function assignPermissions($permissionIds)
    {
        // 先删除现有权限关联
        $this->permissions()->detach();
        
        // 添加新的权限关联
        if (!empty($permissionIds)) {
            return $this->permissions()->attach($permissionIds);
        }
        
        return true;
    }

    /**
     * 分配菜单
     * @param array $menuIds 菜单ID数组
     * @return bool
     */
    public function assignMenus($menuIds)
    {
        // 先删除现有菜单关联
        $this->menus()->detach();
        
        // 添加新的菜单关联
        if (!empty($menuIds)) {
            return $this->menus()->attach($menuIds);
        }
        
        return true;
    }

    /**
     * 检查角色是否被使用
     * @param int $id 角色ID
     * @return bool
     */
    public function isUsed($id)
    {
        // 检查是否有管理员使用此角色
        $adminCount = $this->admins()->where('role_id', $id)->count();
        
        return $adminCount > 0;
    }

    /**
     * 获取角色的权限代码列表
     * @return array
     */
    public function getPermissionCodes()
    {
        $permissions = $this->permissions;
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
    public function getMenuIds()
    {
        $menus = $this->menus;
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
    public function hasPermission($permission)
    {
        $permissions = $this->permissions;
        
        foreach ($permissions as $perm) {
            if ($perm->code === $permission) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 获取启用的角色列表（用于下拉选择）
     * @return \think\Collection
     */
    public function getEnabledList()
    {
        return $this->enabled()->order('sort asc, id desc')->select();
    }
}