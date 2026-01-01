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
     * 初始化搜索字段配置
     */
    protected static function boot(): void
    {
        parent::boot();

        // 设置管理员模型的搜索字段配置
        static::setSearchLikeFields(['username', 'nickname']);
        static::setSearchEqualFields(['phone', 'email', 'status', 'gender', 'deleted']);
        static::setSearchKeywordFields(['username', 'nickname', 'phone']);
        static::setSearchRangeFields(['last_login_time']);
    }

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
     * 自动追加的字段
     * @var array
     */
    protected $appends = ['role_ids'];

    /**
     * 字段类型转换
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'deleted' => 'boolean',
        'last_login_time' => 'datetime:Y-m-d H:i:s',
        'last_login_ip' => 'string',
        'created_at' => 'datetime:Y-m-d',
        'updated_at' => 'datetime:Y-m-d'
    ];

    /**
     * 注册模型事件
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
     * 关联管理员角色中间表
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
        $query = parent::handleSearch($query, $params);

        // 按角色筛选
        if (Arr::get($params, 'role_id')) {
            $roleId = Arr::get($params, 'role_id');
            $query->whereHas('roles', static function (Builder $q) use ($roleId) {
                $q->where('role_id', $roleId);
            });
        }
        $query->with(['adminRoles:admin_id,role_id']);

        return $query;
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
     * 获取角色ID数组访问器
     * @return array
     */
    public function getRoleIdsAttribute(): array
    {
        if ($this->relationLoaded('adminRoles')) {
            return $this->adminRoles->pluck('role_id')->toArray();
        }

        return $this->adminRoles()->pluck('role_id')->toArray();
    }
}