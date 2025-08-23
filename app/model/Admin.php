<?php

namespace plugin\theadmin\app\model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 管理员模型
 */
class Admin extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_admin';

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
        'username', 'password', 'nickname', 'phone', 'email', 'avatar', 'status'
    ];

    /**
     * 隐藏字段
     * @var array
     */
    protected $hidden = ['password'];

    /**
     * 字段类型转换
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'status' => 'boolean',
        'deleted' => 'boolean',
        'last_login_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 关联角色
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'sys_admin_role', 'admin_id', 'role_id');
    }

    /**
     * 获取角色列表
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRoles()
    {
        return $this->roles()->get();
    }

    /**
     * 获取管理员权限
     * @return array
     */
    public function getPermissions(): array
    {
        $permissions = [];
        
        // 通过角色获取权限
        $roles = $this->roles()->with('permissions')->get();
        foreach ($roles as $role) {
            if (isset($role->permissions)) {
                foreach ($role->permissions as $permission) {
                    $permissions[$permission->code] = $permission;
                }
            }
        }
        
        return array_values($permissions);
    }

    /**
     * 获取管理员菜单
     * @return array
     */
    public function getMenus(): array
    {
        $menus = [];
        
        // 通过角色获取菜单
        $roles = $this->roles()->with('menus')->get();
        
        foreach ($roles as $role) {
            if (isset($role->menus)) {
                foreach ($role->menus as $menu) {
                    $menus[$menu->id] = $menu;
                }
            }
        }
        
        return array_values($menus);
    }

    /**
     * 检查是否有指定权限
     * @param string $permission 权限代码
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        
        foreach ($permissions as $perm) {
            if ($perm->code === $permission) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 检查是否有指定角色
     * @param string $roleCode 角色代码
     * @return bool
     */
    public function hasRole(string $roleCode): bool
    {
        $roles = $this->roles()->get();
        
        foreach ($roles as $role) {
            if ($role->code === $roleCode) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 验证密码
     * @param string $password 明文密码
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * 设置密码
     * @param string $password 明文密码
     * @return void
     */
    public function setPassword(string $password): void
    {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * 更新最后登录信息
     * @param string $ip IP地址
     * @return bool
     */
    public function updateLastLogin(string $ip = ''): bool
    {
        return $this->update([
            'last_login_ip' => $ip,
            'last_login_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 分配角色
     * @param array $roleIds 角色ID数组
     * @return bool
     */
    public function assignRoles(array $roleIds): bool
    {
        // 先删除现有角色关联
        $this->roles()->detach();
        
        // 添加新的角色关联
        if (!empty($roleIds)) {
            $this->roles()->attach($roleIds);
        }
        
        return true;
    }

    /**
     * 获取管理员列表（带角色信息）
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function getListWithRoles(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->with('roles');
        
        // 添加其他查询条件
        if (!empty($where)) {
            foreach ($where as $key => $value) {
                if (!in_array($key, ['username', 'nickname', 'phone']) && $value !== '') {
                    $query->where($key, $value);
                }
            }
        }
        
        // 支持用户名搜索
        if (!empty($where['username'])) {
            $query->where('username', 'like', '%' . $where['username'] . '%');
        }
        
        // 支持昵称搜索
        if (!empty($where['nickname'])) {
            $query->where('nickname', 'like', '%' . $where['nickname'] . '%');
        }
        
        // 支持手机号搜索
        if (!empty($where['phone'])) {
            $query->where('phone', 'like', '%' . $where['phone'] . '%');
        }
        
        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
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
     * 创建管理员
     * @param array $data 管理员数据
     * @return static|false
     */
    public function createAdmin(array $data): Admin|bool
    {
        // 检查用户名是否已存在
        if ($this->where('username', $data['username'])->exists()) {
            return false;
        }
        
        // 加密密码
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        return $this->create($data);
    }

    /**
     * 更新管理员
     * @param int $id 管理员ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateAdmin(int $id, array $data): bool
    {
        // 检查用户名是否已存在（排除自己）
        if (isset($data['username']) && $this->where('username', $data['username'])->where('id', '!=', $id)->exists()) {
            return false;
        }
        
        // 如果有密码，进行加密
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            // 如果密码为空，则不更新密码字段
            unset($data['password']);
        }
        
        return $this->where('id', $id)->update($data);
    }
}