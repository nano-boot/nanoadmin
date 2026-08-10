<?php

namespace plugin\nanoadmin\app\model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\model\ModelFactory;
use plugin\nanoadmin\app\model\Role;

/**
 * 管理员模型
 * @property string $password 密码
 * @property string $username 用户名
 * @property string $nickname 昵称
 * @property string $phone 手机号
 * @property string $email 邮箱
 * @property string $avatar 头像
 * @property int $status 状态
 * @property int $gender 性别
 * @property int $dept_id 所属部门ID
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
     * 搜索字段配置（显式声明，避免静态属性继承污染）
     * @var array
     */
    protected static array $searchLikeFields = ['username', 'nickname'];
    protected static array $searchEqualFields = ['phone', 'email', 'status', 'gender', 'deleted_at'];
    protected static array $searchKeywordFields = ['username', 'nickname', 'phone'];
    protected static array $searchRangeFields = ['last_login_time'];

    /**
     * 可批量赋值的属性
     * @var array
     */
    protected $fillable = [
        'username', 'password', 'nickname', 'gender', 'phone', 'email', 'avatar', 'status', 'dept_id', 'created_by'
    ];

    /**
     * 类型转换
     * @var array
     */
    protected $casts = [
        'created_by' => 'integer',
        'dept_id' => 'integer',
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
     * 关联部门
     * @return BelongsTo
     */
    public function dept(): BelongsTo
    {
        return $this->belongsTo(Dept::class, 'dept_id', 'id');
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

        // 按部门筛选（含其全部子部门）
        // 借助 Dept 表的物化 path 字段（如 ",0,1,"）做子树展开：
        //   1) 先用 Dept::getDescendantIds() 通过 LIKE 'path . deptId ,%' 拿到所有子孙 ID
        //   2) 把自身 ID 也合并进去（getDescendantIds 不含自身）
        //   3) 最终 whereIn('dept_id', [...])
        $deptId = Arr::get($params, 'dept_id');
        if ($deptId !== null && $deptId !== '' && (int) $deptId > 0) {
            $deptId = (int) $deptId;
            $deptModel = ModelFactory::dept();
            $descendantIds = $deptModel->getDescendantIds($deptId);
            $deptIds = array_values(array_unique(array_merge([$deptId], $descendantIds)));

            if (!empty($deptIds)) {
                $query->whereIn('dept_id', $deptIds);
            }
        }

        // ===== 数据权限自动过滤 =====
        // 根据当前管理员的角色数据权限，自动限制查询范围
        $query = $this->applyDataScopeFilter($query);

        $query->with(['adminRoles:admin_id,role_id', 'dept:id,name']);

        return $query;
    }

    /**
     * 应用数据权限过滤
     * 根据当前管理员的角色数据权限，自动限制查询范围
     *
     * 数据权限范围：
     * 1. 全部数据 - 不限制
     * 2. 本部门及下级 - 本部门 + 子孙部门
     * 3. 本部门 - 只看本部门
     * 4. 仅本人 - 只看自己（通过 created_by 过滤）
     * 5. 自定义部门 - 使用 sys_role_dept 表配置的部门
     *
     * @param Builder $query
     * @return Builder
     */
    private function applyDataScopeFilter(Builder $query): Builder
    {
        // 获取当前请求
        $request = request();
        if (!$request || !isset($request->admin)) {
            return $query;
        }

        $admin = $request->admin;
        if (!$admin || !isset($admin->id)) {
            return $query;
        }

        // 获取管理员的角色
        $roleIds = $admin->roles()->pluck('id')->toArray();
        if (empty($roleIds)) {
            return $query;
        }

        // 加载所有角色及其数据权限配置
        $roleModel = ModelFactory::role();
        $roles = $roleModel
            ->whereIn('id', $roleIds)
            ->with('depts')
            ->get();

        if ($roles->isEmpty()) {
            return $query;
        }

        // 获取管理员所属部门
        $adminDeptId = $admin->dept_id ?? 0;

        // 合并所有允许访问的部门ID
        $allowedDeptIds = [];
        $hasSelfScope = false; // 是否存在"仅本人"模式

        foreach ($roles as $role) {
            $scope = $role->data_scope ?? Role::DATA_SCOPE_ALL;

            switch ($scope) {
                case Role::DATA_SCOPE_ALL:
                    // 全部数据：不限制，直接返回
                    return $query;

                case Role::DATA_SCOPE_DEPT_AND_CHILD:
                    // 本部门及下级
                    if ($adminDeptId > 0) {
                        $deptModel = ModelFactory::dept();
                        $descendantIds = $deptModel->getDescendantIds($adminDeptId);
                        $allowedDeptIds = array_merge($allowedDeptIds, [$adminDeptId], $descendantIds);
                    }
                    break;

                case Role::DATA_SCOPE_DEPT:
                    // 本部门
                    if ($adminDeptId > 0) {
                        $allowedDeptIds[] = $adminDeptId;
                    }
                    break;

                case Role::DATA_SCOPE_SELF:
                    // 仅本人：只看自己创建的数据（通过 created_by 过滤）
                    $hasSelfScope = true;
                    break;

                case Role::DATA_SCOPE_CUSTOM:
                    // 自定义部门
                    foreach ($role->depts as $dept) {
                        $deptModel = ModelFactory::dept();
                        $descendantIds = $deptModel->getDescendantIds($dept->id);
                        $allowedDeptIds = array_merge($allowedDeptIds, [$dept->id], $descendantIds);
                    }
                    break;
            }
        }

        // 去除重复
        $allowedDeptIds = array_values(array_unique($allowedDeptIds));

        // 应用过滤条件
        if ($hasSelfScope) {
            // 仅本人模式：只看自己创建的数据（通过 created_by 过滤）
            $query->where('created_by', $admin->id);
        }

        if (!empty($allowedDeptIds)) {
            $query->whereIn('dept_id', $allowedDeptIds);
        }

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

    /**
     * 获取头像访问器 - 直接返回带域名的完整URL
     * @return string
     */
    public function getAvatarAttribute($value): string
    {
        if (empty($value)) {
            return ''; // 默认头像
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $domain = domain(); 

        if (str_starts_with($value, '/')) {
            return $domain . $value;
        }

        return $domain . '/' . $value;
    }
}