<?php

namespace plugin\theadmin\app\model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;

/**
 * 管理员模型
 * @property string $password 密码
 * @property string $username 用户名
 * @property string $nickname 昵称
 * @property string $phone 手机号
 * @property string $email 邮箱
 * @property string $avatar 头像
 * @property int $status 状态
 * @property int $id
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
        'username', 'password', 'nickname','gender', 'phone', 'email', 'avatar', 'status'
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
        'deleted' => 'boolean',
        'last_login_time' => 'string',
        'last_login_ip' => 'string',
        'created_at' => 'string',
        'updated_at' => 'string'
    ];

    /**
     * 模型启动方法，注册模型事件
     */
    protected static function booted(): void
    {
        static::updating(function (Admin $admin) {
            if ($admin->id === 1 && $admin->isDirty('username')) {
                throw new ApiException(Code::FORBIDDEN, '系统默认管理员不允许修改用户名');
            }
        });

        static::deleting(function (Admin $admin) {
            if ($admin->id === 1) {
                throw new ApiException(Code::FORBIDDEN, '系统默认管理员不允许删除');
            }
        });
    }

    /**
     * 关联角色
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'sys_admin_role', 'admin_id', 'role_id');
    }

    /**
     * 关联管理员角色中间表（用于高效查询角色ID）
     * @return HasMany
     */
    public function adminRoles(): HasMany
    {
        return $this->hasMany(AdminRole::class, 'admin_id', 'id');
    }

    /**
     * 获取角色列表
     * @return Collection
     */
    public function getRoles(): Collection
    {
        return $this->roles()->get();
    }

    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'username'), static function (Builder $query, $username) {
                $query->where('username', 'like', '%' . $username . '%');
            })
            ->when(Arr::get($params, 'phone'), static function (Builder $query, $phone) {
                $query->where('phone', $phone);
            })
            ->when(Arr::get($params, 'email'), static function (Builder $query, $email) {
                $query->where('email', $email);
            })
            ->when(Arr::exists($params, 'status'), static function (Builder $query) use ($params) {
                $query->where('status', Arr::get($params, 'status'));
            })
            // 性别筛选
            ->when(Arr::exists($params, 'gender'), static function (Builder $query) use ($params) {
                $query->where('gender', Arr::get($params, 'gender'));
            })
            ->when(Arr::exists($params, 'nickname'), static function (Builder $query) use ($params) {
                $query->where('nickname', 'like', '%' . Arr::get($params, 'nickname') . '%');
            })
            ->when(Arr::exists($params, 'created_at'), static function (Builder $query) use ($params) {
                $query->whereBetween('created_at', [
                    Arr::get($params, 'created_at')[0] . ' 00:00:00',
                    Arr::get($params, 'created_at')[1] . ' 23:59:59',
                ]);
            })
            // 最后登录时间范围筛选
            ->when(Arr::exists($params, 'last_login_time'), static function (Builder $query) use ($params) {
                $query->whereBetween('last_login_time', [
                    Arr::get($params, 'last_login_time')[0] . ' 00:00:00',
                    Arr::get($params, 'last_login_time')[1] . ' 23:59:59',
                ]);
            })
            // ID列表筛选
            ->when(Arr::get($params, 'admin_ids'), static function (Builder $query, $adminIds) {
                $query->whereIn('id', $adminIds);
            })
            // 角色筛选（通过关联表）
            ->when(Arr::get($params, 'role_id'), static function (Builder $query, $roleId) {
                $query->whereHas('roles', static function (Builder $query) use ($roleId) {
                    $query->where('role_id', $roleId);
                });
            })
            // 软删除筛选（默认只查询未删除的记录）
            ->when(!Arr::exists($params, 'deleted') || Arr::get($params, 'deleted') === false, static function (Builder $query) {
                $query->where('deleted', false);
            })
            // 如果明确要查询已删除的记录
            ->when(Arr::exists($params, 'deleted') && Arr::get($params, 'deleted') === true, static function (Builder $query) {
                $query->where('deleted', true);
            })
            // 关联角色信息
            ->with(['adminRoles:admin_id,role_id']);
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
     * @param $value
     * @return void
     */
    public function setPasswordAttribute($value): void
    {
        if (is_null($value) || trim($value) === '') {
            $this->attributes['password'] = $this->getOriginal('password');
        } else  {
            $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
        }
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
        if ($this->id === 1) {
            // 系统默认管理员不允许修改角色信息
            return false;
        }
        
        // 同步角色关联
        $this->roles()->sync($roleIds);
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
        
        // 密码将通过修改器自动加密，无需手动处理
        return $this->create($data);
    }
}