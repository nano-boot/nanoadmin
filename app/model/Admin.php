<?php

namespace plugin\theadmin\app\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\model\Pivot;
use think\model\relation\BelongsToMany;
use think\Paginator;

/**
 * 管理员模型
 * @property mixed $roles 关联角色
 * @property string $password 密码
 */
class Admin extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $name = 'sys_admin';

    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 隐藏字段
     * @var array
     */
    protected array $hidden = ['password'];

    /**
     * 字段类型转换
     * @var array
     */
    protected array $type = [
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
        return $this->belongsToMany(Role::class, 'sys_admin_role', 'role_id', 'admin_id');
    }

    /**
     * 获取管理员权限
     * @return array
     */
    public function getPermissions(): array
    {
        $permissions = [];
        
        // 通过角色获取权限
        $roles = $this->roles;
        
        foreach ($roles as $role) {
            $rolePermissions = $role->permissions;
            foreach ($rolePermissions as $permission) {
                $permissions[$permission->code] = $permission;
            }
        }
        
        return array_values($permissions);
    }

    /**
     * 获取管理员菜单
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenus(): array
    {
        $menus = [];
        
        // 通过角色获取菜单
        $roles = $this->roles()->with('menus')->select();
        
        foreach ($roles as $role) {
            foreach ($role->menus as $menu) {
                $menus[$menu->id] = $menu;
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
        $roles = $this->roles;
        
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
        return $this->save([
            'last_login_ip' => $ip,
            'last_login_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 分配角色
     * @param array $roleIds 角色ID数组
     * @return array|bool|Pivot
     * @throws DbException
     */
    public function assignRoles(array $roleIds): bool|array|Pivot
    {
        // 先删除现有角色关联
        $this->roles()->detach();
        
        // 添加新的角色关联
        if (!empty($roleIds)) {
            return $this->roles()->attach($roleIds);
        }
        
        return true;
    }

    /**
     * 获取管理员列表（带角色信息）
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return Paginator
     * @throws DbException
     */
    public function getListWithRoles(array $where = [], int $page = 1, int $limit = 15): Paginator
    {
        $query = $this->with('roles')->where($where);
        
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
        
        return $query->order('id desc')->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
    }

    /**
     * 创建管理员
     * @param array $data 管理员数据
     * @return static|false
     */
    public function createAdmin(array $data): Admin|bool|static
    {
        // 检查用户名是否已存在
        if ($this->checkExists(['username' => $data['username']])) {
            return false;
        }
        
        // 加密密码
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        return $this->save($data) ? $this : false;
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
        if (isset($data['username']) && $this->checkExists(['username' => $data['username']], $id)) {
            return false;
        }
        
        // 如果有密码，进行加密
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            // 如果密码为空，则不更新密码字段
            unset($data['password']);
        }
        
        return $this->where('id', $id)->update($data) !== false;
    }
}