<?php

namespace plugin\theadmin\app\model;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 菜单模型
 * @property mixed $roles
 * @property mixed $parent
 * @property mixed $children
 * @property array $roles_array 角色权限数组
 * @property array $auth_list_array 权限按钮列表数组
 */
class Menu extends BaseModel
{
    /**
     * 菜单类型常量
     */
    const TYPE_DIRECTORY = 'D';  // 目录
    const TYPE_MENU = 'M';       // 菜单页面
    const TYPE_BUTTON = 'B';     // 权限按钮
    const TYPE_LINK = 'L';       // 外链
    const TYPE_IFRAME = 'I';     // 内嵌页面

    /**
     * 菜单类型映射
     */
    const TYPE_MAP = [
        self::TYPE_DIRECTORY => '目录',
        self::TYPE_MENU => '菜单',
        self::TYPE_BUTTON => '按钮',
        self::TYPE_LINK => '外链',
        self::TYPE_IFRAME => '内嵌'
    ];

    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_menu';

    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 搜索字段配置（显式声明，避免静态属性继承污染）
     * @var array
     */
    protected static array $searchLikeFields = ['title'];
    protected static array $searchEqualFields = ['status', 'type'];
    protected static array $searchKeywordFields = ['title'];
    protected static array $searchRangeFields = [];

    /**
     * JSON字段
     * @var array
     */
    protected array $json = ['roles', 'auth_list'];

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'title',
        'icon',
        'path',
        'component',
        'redirect',
        'permission',
        'hide_tab',
        'link',
        'iframe',
        'status',
        'sort',
        'deleted',
        'roles',
        'auth_list',
        // 底层数据库字段（snake_case）
        'cache',           // 对应虚拟属性 keepAlive
        'hide',          // 对应虚拟属性 isHide
        'hide_tab',        // 对应虚拟属性 isHideTab
        'fixed_tab',       // 对应虚拟属性 fixedTab
        'full_page',       // 对应虚拟属性 isFullPage
        'show_badge',      // 对应虚拟属性 showBadge
        'badge_text',      // 对应虚拟属性 showTextBadge
        'active_path',     // 对应虚拟属性 activePath
        // 注意: 不要在 $fillable 中包含驼峰式虚拟属性(keepAlive, fixedTab等)
        // 这些虚拟属性通过访问器/修改器自动映射到上面的 snake_case 字段
    ];

    /**
     * 字段类型转换 (Laravel Eloquent 标准配置)
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'parent_id' => 'integer',
        'type' => 'string',
        'hide' => 'boolean',
        'hide_tab' => 'boolean',
        'cache' => 'boolean',
        'fixed_tab' => 'boolean',
        'full_page' => 'boolean',
        'iframe' => 'boolean',
        'show_badge' => 'boolean',
        'status' => 'boolean',
        'sort' => 'integer',
        'deleted' => 'boolean',
    ];

    /**
     * 需要隐藏的字段（使用驼峰命名代替）
     * @var array
     */
    protected $hidden = [
        'cache',           // 用 keepAlive 代替
        'hide',          // 用 isHide 代替
        'hide_tab',        // 用 isHideTab 代替
        'fixed_tab',       // 用 fixedTab 代替
        'full_page',       // 用 isFullPage 代替
        'show_badge',      // 用 showBadge 代替
        'badge_text',      // 用 showTextBadge 代替
        'active_path',     // 用 activePath 代替
        'created_at',      // 用 createdAt 代替
        'updated_at',      // 用 updatedAt 代替
    ];

    /**
     * 模型启动事件
     */
    protected static function boot(): void
    {
        parent::boot();

        // 搜索字段已通过静态属性声明，无需重复设置
    }

    /**
     * 追加到模型数组表单的访问器
     * @var array
     */
    protected $appends = [
        'keepAlive',
        'isHide',
        'isHideTab',
        'fixedTab',
        'isFullPage',
        'showBadge',
        'showTextBadge',
        'activePath',
        'createdAt',
        'updatedAt',
    ];

    /**
     * JSON字段序列化处理
     * @param mixed $value
     * @param array $data
     * @return array|null
     */
    public function setRolesAttr($value, array $data): ?array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return is_array($value) ? $value : null;
    }

    /**
     * JSON字段反序列化处理
     * @param mixed $value
     * @param array $data
     * @return array
     */
    public function getRolesAttr($value, array $data): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }

    /**
     * 权限按钮列表序列化处理
     * @param mixed $value
     * @param array $data
     * @return array|null
     */
    public function setAuthListAttr($value, array $data): ?array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return is_array($value) ? $value : null;
    }

    /**
     * 权限按钮列表反序列化处理
     * @param mixed $value
     * @param array $data
     * @return array
     */
    public function getAuthListAttr($value, array $data): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }

    // ==================== 字段名称映射访问器  ====================

      /**
     * keepAlive 访问器 (cache 字段的驼峰式别名)
     * @return bool
     */
    public function getKeepAliveAttribute(): bool
    {
        return (bool)($this->attributes['cache'] ?? false);
    }

     /**
     * isHide 访问器 (hide 字段的驼峰式别名)
     * @return bool
     */
    public function getIsHideAttribute(): bool
    {
        return (bool)($this->attributes['hide'] ?? false);
    }

    /**
     * isHideTab 访问器 (hide_tab 字段的驼峰式别名)
     * @return bool
     */
    public function getIsHideTabAttribute(): bool
    {
        return (bool)($this->attributes['hide_tab'] ?? false);
    }

    /**
     * fixedTab 访问器 (fixed_tab 字段的驼峰式别名)
     * @return bool
     */
    public function getFixedTabAttribute(): bool
    {
        return (bool)($this->attributes['fixed_tab'] ?? false);
    }

    /**
     * isFullPage 访问器 (full_page 字段的驼峰式别名)
     * @return bool
     */
    public function getIsFullPageAttribute(): bool
    {
        return (bool)($this->attributes['full_page'] ?? false);
    }

    /**
     * showBadge 访问器 (show_badge 字段的驼峰式别名)
     * @return bool
     */
    public function getShowBadgeAttribute(): bool
    {
        return (bool)($this->attributes['show_badge'] ?? false);
    }

    /**
     * showTextBadge 访问器 (badge_text 字段的驼峰式别名)
     * @return string
     */
    public function getShowTextBadgeAttribute(): string
    {
        return $this->attributes['badge_text'] ?? '';
    }

    /**
     * activePath 访问器 (active_path 字段的驼峰式别名)
     * @return string
     */
    public function getActivePathAttribute(): string
    {
        return $this->attributes['active_path'] ?? '';
    }

    /**
     * createdAt 访问器 (created_at 字段的驼峰式别名)
     * @return string|null
     */
    public function getCreatedAtAttribute(): ?string
    {
        return $this->attributes['created_at'] ?? null;
    }

    /**
     * updatedAt 访问器 (updated_at 字段的驼峰式别名)
     * @return string|null
     */
    public function getUpdatedAtAttribute(): ?string
    {
        return $this->attributes['updated_at'] ?? null;
    }

    // ==================== 字段名称映射修改器  ====================

      /**
     * keepAlive 修改器 (cache 字段的驼峰式别名)
     * @param bool $value
     */
    public function setKeepAliveAttribute(bool $value): void
    {
        $this->attributes['cache'] = $value;
    }

     /**
     * isHide 修改器 (hide 字段的驼峰式别名)
     * @return bool
     */
    public function setIsHideAttribute($value): void
    {
        $this->attributes['hide']= $value;
    }

    /**
     * isHideTab 修改器 (自动映射到 hide_tab 字段)
     * @param bool $value
     */
    public function setIsHideTabAttribute(bool $value): void
    {
        $this->attributes['hide_tab'] = $value;
    }

    /**
     * fixedTab 修改器 (自动映射到 fixed_tab 字段)
     * @param bool $value
     */
    public function setFixedTabAttribute(bool $value): void
    {
        $this->attributes['fixed_tab'] = $value;
    }

    /**
     * isFullPage 修改器 (自动映射到 full_page 字段)
     * @param bool $value
     */
    public function setIsFullPageAttribute(bool $value): void
    {
        $this->attributes['full_page'] = $value;
    }

    /**
     * showBadge 修改器 (自动映射到 show_badge 字段)
     * @param bool $value
     */
    public function setShowBadgeAttribute(bool $value): void
    {
        $this->attributes['show_badge'] = $value;
    }

    /**
     * showTextBadge 修改器 (自动映射到 badge_text 字段)
     * @param string $value
     */
    public function setShowTextBadgeAttribute(string $value): void
    {
        $this->attributes['badge_text'] = $value;
    }

    /**
     * activePath 修改器 (自动映射到 active_path 字段)
     * @param string $value
     */
    public function setActivePathAttribute(string $value): void
    {
        $this->attributes['active_path'] = $value;
    }

    /**
     * createdAt 修改器 (自动映射到 created_at 字段)
     * @param string|null $value
     */
    public function setCreatedAtAttribute(?string $value): void
    {
        $this->attributes['created_at'] = $value;
    }

    /**
     * updatedAt 修改器 (自动映射到 updated_at 字段)
     * @param string|null $value
     */
    public function setUpdatedAtAttribute(?string $value): void
    {
        $this->attributes['updated_at'] = $value;
    }

    /**
     * 关联角色
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'sys_role_menu', 'menu_id', 'role_id');
    }

    /**
     * 关联父菜单
     * @return HasOne
     */
    public function parent(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'parent_id');
    }

    /**
     * 关联子菜单
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')->orderBy('sort', 'asc')->orderBy('id', 'asc');
    }

    /**
     * 获取菜单树形结构
     *
     * 【性能优化】：原实现为递归查询，每个节点触发一次 SELECT，N+1 问题极严重。
     * 现改为：一次性加载全部菜单数据，在内存中构建树形结构，数据库只查询 1 次。
     *
     * @param int $parentId 树的根节点父ID（0 = 全树）
     * @param bool $onlyEnabled 是否只获取启用的菜单
     * @param bool $includeDeleted 是否包含已删除的菜单
     * @return array
     */
    public function getTree(int $parentId = 0, bool $onlyEnabled = true, bool $includeDeleted = false): array
    {
        // ✅ 只查询一次数据库，加载全部符合条件的菜单
        $query = $this->newQuery();
        if ($onlyEnabled) {
            $query->where('status', 1);
        }
        if (!$includeDeleted) {
            $query->where('deleted', false);
        }
        $allMenus = $query->orderBy('sort', 'asc')
                          ->orderBy('id', 'asc')
                          ->get()
                          ->toArray();

        // ✅ 在内存中以 id 为键建立 Map，O(n) 时间复杂度
        $menuMap = [];
        foreach ($allMenus as $menu) {
            $menu['children'] = [];
            $menuMap[$menu['id']] = $menu;
        }

        // ✅ 单次遍历构建树形结构，无任何数据库调用
        $tree = [];
        foreach ($menuMap as &$menu) {
            if ($menu['parent_id'] == $parentId) {
                // 顶级节点（相对于指定的 parentId）
                $tree[] = &$menu;
            } elseif (isset($menuMap[$menu['parent_id']])) {
                // 挂载到父节点的 children 下
                $menuMap[$menu['parent_id']]['children'][] = &$menu;
            }
        }
        unset($menu); // 释放引用，避免后续误用

        return $tree;
    }

    /**
     * 获取管理员菜单树。
     *
     * 返回规则：
     * - 管理员不存在：返回空数组（异常场景由调用方决定是否上抛）
     * - 超级管理员：返回完整菜单树，不再通过空数组表达“全量放行”
     * - 普通管理员：返回其可访问菜单树；若无任何授权菜单则返回空数组
     *
     * @param int $adminId 管理员ID
     * @return array
     */
    public function getAdminMenuTree(int $adminId): array
    {
        // ✅ 一次性预加载 roles 和 roles.menus，避免重复查询角色
        $admin = Admin::with(['roles.menus'])->find($adminId);
        if (!$admin) {
            return [];
        }

        // ✅ 使用集合方法检查超级管理员，无需手动循环
        $isSuperAdmin = $admin->roles->contains('code', 'R_SUPER');

        // 超级管理员获取所有菜单
        if ($isSuperAdmin) {
            return $this->getTree();
        }

        // ✅ 直接从已预加载的关联数据中收集菜单ID，无需额外查询
        $menuIds = [];
        foreach ($admin->roles as $role) {
            foreach ($role->menus as $menu) {
                $menuIds[] = $menu->id;
            }
        }

        if (empty($menuIds)) {
            return [];
        }

        // 获取菜单数据并构建树形结构
        return $this->buildTreeFromIds(array_unique($menuIds));
    }

    /**
     * 根据菜单ID数组构建树形结构
     * @param array $menuIds 菜单ID数组
     * @return array
     */
    private function buildTreeFromIds(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }
        
        // 获取所有相关菜单
        $menus = $this->whereIn('id', $menuIds)
                     ->enabled()
                     ->orderBy('sort', 'asc')
                     ->orderBy('id', 'asc')
                     ->get();
        
        // 构建菜单映射
        $menuMap = [];
        foreach ($menus as $menu) {
            $menuMap[$menu->id] = $menu->toArray();
            $menuMap[$menu->id]['children'] = [];
        }
        
        // 构建树形结构
        $tree = [];
        foreach ($menuMap as $menu) {
            if ($menu['parent_id'] == 0) {
                $tree[] = &$menuMap[$menu['id']];
            } else {
                if (isset($menuMap[$menu['parent_id']])) {
                    $menuMap[$menu['parent_id']]['children'][] = &$menuMap[$menu['id']];
                }
            }
        }
        
        return $tree;
    }

    /**
     * 获取菜单列表（平铺结构，带层级标识）
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return LengthAwarePaginator
     */
    public function getListWithLevel(array $where = [], int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        $query = $this->where($where);
        
        // 支持菜单名称搜索
        if (!empty($where['name'])) {
            $query->where('name', 'like', '%' . $where['name'] . '%');
        }
        
        // 支持菜单标题搜索
        if (!empty($where['title'])) {
            $query->where('title', 'like', '%' . $where['title'] . '%');
        }
        
        // 支持菜单类型筛选
        if (isset($where['menu_type'])) {
            $query->where('menu_type', $where['menu_type']);
        }
        
        return $query->orderBy('sort', 'asc')
                    ->orderBy('id', 'asc')
                    ->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * 创建菜单
     * @param array $data 菜单数据
     * @return static|false
     */
    public function createMenu(array $data): Menu|bool|static
    {
        // 验证父菜单是否存在
        if (!empty($data['parent_id']) && $data['parent_id'] > 0) {
            $parent = $this->find($data['parent_id']);
            if (!$parent) {
                return false;
            }
        }
        
        // 设置默认排序值
        if (!isset($data['sort'])) {
            $data['sort'] = $this->getNextSort(['parent_id' => $data['parent_id'] ?? 0]);
        }
        
        return $this->save($data) ? $this : false;
    }

    /**
     * 更新菜单
     * @param int $id 菜单ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateMenu(int $id, array $data): bool
    {
        // 获取菜单实例
        $menu = $this->find($id);
        if (!$menu) {
            return false;
        }

        // 验证父菜单是否存在（如果有设置）
        if (!empty($data['parent_id']) && $data['parent_id'] > 0) {
            // 不能将自己设为父菜单
            if ($data['parent_id'] == $id) {
                return false;
            }

            $parent = $this->find($data['parent_id']);
            if (!$parent) {
                return false;
            }

            // 检查是否会形成循环引用
            if ($this->wouldCreateCircularReference($id, $data['parent_id'])) {
                return false;
            }
        }

        // 使用模型实例逐个设置属性（会自动触发修改器）
        // 这样 keepAlive 会通过 setKeepAliveAttribute() 自动转换为 cache
        foreach ($data as $key => $value) {
            $menu->$key = $value;
        }

        // 保存更改（会触发 updated_at 自动更新）
        return $menu->save();
    }

    /**
     * 转换驼峰式字段名为 snake_case
     *
     * 注意: updateMenu() 方法已改用模型实例的 save() 方式,会自动触发修改器。
     * 此方法保留用于其他可能需要批量更新的场景(如批量导入、批量修改等)。
     *
     * 使用场景:
     * - 批量更新: Menu::where(...)->update($this->convertCamelCaseToSnakeCase($data))
     * - 批量插入: Menu::insert($this->convertCamelCaseToSnakeCase($data))
     *
     * @param array $data 原始数据(可能包含驼峰式字段名)
     * @return array 转换后的数据(所有字段名转换为 snake_case)
     */
    private function convertCamelCaseToSnakeCase(array $data): array
    {
        // 定义驼峰式到 snake_case 的映射
        $fieldMapping = [
            'keepAlive' => 'cache',
            'fixedTab' => 'fixed_tab',
            'isHide' => 'hide',
            'isHideTab' => 'hide_tab',
            'isFullPage' => 'full_page',
            'showBadge' => 'show_badge',
            'showTextBadge' => 'badge_text',
            'activePath' => 'active_path',
            'createdAt' => 'created_at',
            'updatedAt' => 'updated_at',
        ];

        $converted = [];

        foreach ($data as $key => $value) {
            // 如果是驼峰式字段,转换为 snake_case
            if (isset($fieldMapping[$key])) {
                $converted[$fieldMapping[$key]] = $value;
            } else {
                // 保持原字段名
                $converted[$key] = $value;
            }
        }

        return $converted;
    }

    /**
     * 检查是否会形成循环引用
     * @param int $menuId 当前菜单ID
     * @param int $parentId 要设置的父菜单ID
     * @return bool
     */
    private function wouldCreateCircularReference(int $menuId, int $parentId): bool
    {
        if ($parentId == 0) {
            return false;
        }

        // 检查父菜单的所有祖先菜单
        $currentParentId = $parentId;
        $visited = [];

        while ($currentParentId > 0) {
            if ($currentParentId == $menuId) {
                return true; // 发现循环引用
            }

            if (in_array($currentParentId, $visited)) {
                break; // 防止无限循环
            }

            $visited[] = $currentParentId;
            $parent = $this->find($currentParentId);
            $currentParentId = $parent ? $parent->parent_id : 0;
        }

        return false;
    }

    /**
     * 删除菜单（检查是否有子菜单）
     * @param int $id 菜单ID
     * @return bool
     */
    public function deleteMenu(int $id): bool
    {
        // 检查是否有子菜单
        if ($this->hasChildren($id)) {
            return false;
        }
        
        // 检查是否被角色使用
        if ($this->isUsed($id)) {
            return false;
        }
        
        return $this->where('id', $id)->delete() !== false;
    }

    /**
     * 检查菜单是否有子菜单
     * @param int $id 菜单ID
     * @return bool
     */
    public function hasChildren(int $id): bool
    {
        return $this->where('parent_id', $id)->count() > 0;
    }

    /**
     * 检查菜单是否被角色使用
     * @param int $id 菜单ID
     * @return bool
     */
    public function isUsed(int $id): bool
    {
        // 检查是否有角色使用此菜单
        $roleCount = $this->roles()->where('menu_id', $id)->count();
        
        return $roleCount > 0;
    }

    /**
     * 批量更新菜单排序
     * @param array $sortData 排序数据 [['id' => 1, 'sort' => 10, 'parent_id' => 0], ...]
     * @return bool
     */
    public function batchUpdateSort(array $sortData): bool
    {
        if (empty($sortData)) {
            return false;
        }
        
        DB::beginTransaction();
        try {
            foreach ($sortData as $item) {
                if (isset($item['id'])) {
                    $updateData = [];
                    if (isset($item['sort'])) {
                        $updateData['sort'] = $item['sort'];
                    }
                    if (isset($item['parent_id'])) {
                        $updateData['parent_id'] = $item['parent_id'];
                    }
                    
                    if (!empty($updateData)) {
                        $this->where('id', $item['id'])->update($updateData);
                    }
                }
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    /**
     * 获取菜单路径（面包屑）
     * @param int $menuId 菜单ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenuPath(int $menuId): array
    {
        $path = [];
        $currentId = $menuId;
        
        while ($currentId > 0) {
            $menu = $this->find($currentId);
            if (!$menu) {
                break;
            }
            
            array_unshift($path, [
                'id' => $menu->id,
                'name' => $menu->name,
                'title' => $menu->title,
                'path' => $menu->path
            ]);
            
            $currentId = $menu->parent_id;
        }
        
        return $path;
    }

    /**
     * 获取菜单深度
     * @param int $menuId 菜单ID
     * @return int
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenuDepth(int $menuId): int
    {
        $depth = 0;
        $currentId = $menuId;
        
        while ($currentId > 0) {
            $menu = $this->find($currentId);
            if (!$menu) {
                break;
            }
            
            $depth++;
            $currentId = $menu->parent_id;
        }
        
        return $depth;
    }

    /**
     * 验证菜单名称格式
     * @param string $name 菜单名称
     * @return bool
     */
    public static function validateName(string $name): bool
    {
        // 菜单名称只能包含字母、数字、下划线、中划线、中文字符，长度2-100
        return preg_match('/^[a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]{2,100}$/u', $name);
    }

    /**
     * 验证菜单标题格式
     * @param string $title 菜单标题
     * @return bool
     */
    public static function validateTitle(string $title): bool
    {
        // 菜单标题长度2-100，不能为空
        return !empty(trim($title)) && mb_strlen(trim($title)) >= 2 && mb_strlen(trim($title)) <= 100;
    }

    /**
     * 验证路由路径格式
     * @param string $path 路由路径
     * @return bool
     */
    public static function validatePath(string $path): bool
    {
        if (empty($path)) {
            return true; // 路径可以为空
        }
        
        // 路径必须以/开头，只能包含字母、数字、下划线、中划线、斜杠，长度不超过200
        return preg_match('/^\/[a-zA-Z0-9_\-\/]*$/', $path) && strlen($path) <= 200;
    }

    /**
     * 验证组件路径格式
     * @param string $component 组件路径
     * @return bool
     */
    public static function validateComponent(string $component): bool
    {
        if (empty($component)) {
            return true; // 组件路径可以为空
        }
        
        // 组件路径只能包含字母、数字、下划线、中划线、斜杠、点号，长度不超过200
        return preg_match('/^[a-zA-Z0-9_\-\/\.]*$/', $component) && strlen($component) <= 200;
    }

    /**
     * 验证权限标识格式
     * @param string $permission 权限标识
     * @return bool
     */
    public static function validatePermission(string $permission): bool
    {
        if (empty($permission)) {
            return true; // 权限标识可以为空
        }
        
        // 权限标识只能包含字母、数字、下划线、中划线、冒号，长度不超过100
        return preg_match('/^[a-zA-Z0-9_\-:]*$/', $permission) && strlen($permission) <= 100;
    }

    /**
     * 验证外链URL格式
     * @param string $url 外链URL
     * @return bool
     */
    public static function validateUrl(string $url): bool
    {
        if (empty($url)) {
            return true; // URL可以为空
        }
        
        // 验证URL格式并检查长度
        return filter_var($url, FILTER_VALIDATE_URL) !== false && strlen($url) <= 500;
    }

    /**
     * 验证菜单类型
     * @param string $type 菜单类型
     * @return bool
     */
    public static function validateMenuType(string $type): bool
    {
        return in_array($type, [
            self::TYPE_DIRECTORY, 
            self::TYPE_MENU, 
            self::TYPE_BUTTON, 
            self::TYPE_LINK, 
            self::TYPE_IFRAME
        ]);
    }

    /**
     * 验证角色权限数组格式
     * @param array $roles 角色权限数组
     * @return bool
     */
    public static function validateRoles(array $roles): bool
    {
        if (empty($roles)) {
            return true; // 角色数组可以为空
        }
        
        // 检查是否为字符串数组
        foreach ($roles as $role) {
            if (!is_string($role) || empty($role)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 验证权限按钮列表格式
     * @param array $authList 权限按钮列表
     * @return bool
     */
    public static function validateAuthList(array $authList): bool
    {
        if (empty($authList)) {
            return true; // 权限列表可以为空
        }
        
        // 检查每个权限按钮的格式
        foreach ($authList as $auth) {
            if (!is_array($auth) || !isset($auth['title']) || !isset($auth['authMark'])) {
                return false;
            }
            
            if (!is_string($auth['title']) || !is_string($auth['authMark'])) {
                return false;
            }
            
            if (empty($auth['title']) || empty($auth['authMark'])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 检查菜单是否激活
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status == 1 && !$this->deleted;
    }

    /**
     * 获取菜单类型文本
     * @return string
     */
    public function getMenuTypeText(): string
    {
        return self::TYPE_MAP[$this->type] ?? '未知';
    }

    /**
     * 获取菜单统计信息
     * @return array
     */
    public function getStats(): array
    {
        return [
            'children_count' => $this->children()->count(),
            'role_count' => $this->roles()->count(),
            'depth' => $this->getMenuDepth($this->id),
            'is_active' => $this->isActive()
        ];
    }

    /**
     * 获取所有顶级菜单
     * @param bool $onlyEnabled 是否只获取启用的菜单
     * @param bool $includeDeleted 是否包含已删除的菜单
     * @return Collection
     */
    public function getTopLevelMenus(bool $onlyEnabled = true, bool $includeDeleted = false): Collection
    {
        $query = $this->where('parent_id', 0);
        
        if ($onlyEnabled) {
            $query->where('status', 1);
        }
        
        if (!$includeDeleted) {
            $query->where('deleted', false);
        }
        
        return $query->orderBy('sort', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
    }

    /**
     * 获取指定菜单的所有子孙菜单ID
     * @param int $menuId 菜单ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getDescendantIds(int $menuId): array
    {
        $ids = [];
        $children = $this->where('parent_id', $menuId)->get();
        
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child->id));
        }
        
        return $ids;
    }

    /**
     * 获取菜单的完整路径字符串
     * @param int $menuId 菜单ID
     * @param string $separator 分隔符
     * @return string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getFullPath(int $menuId, string $separator = ' > '): string
    {
        $path = $this->getMenuPath($menuId);
        $titles = array_column($path, 'title');
        
        return implode($separator, $titles);
    }

    /**
     * 根据权限标识获取菜单
     * @param string $permission 权限标识
     * @return Collection
     */
    public function getByPermission(string $permission): Collection
    {
        return $this->where('permission', $permission)
                   ->enabled()
                   ->orderBy('sort', 'asc')
                   ->orderBy('id', 'asc')
                   ->get();
    }

    /**
     * 获取启用的菜单列表（用于下拉选择）
     * @return Collection
     */
    public function getEnabledList(): Collection
    {
        return $this->where('status', 1)
                   ->where('deleted', false)
                   ->orderBy('sort', 'asc')
                   ->orderBy('id', 'asc')
                   ->get();
    }

    /**
     * 构建前端路由配置
     * @param int $adminId 管理员ID（可选）
     * @return array
     */
    public function buildRouteConfig(int $adminId = 0): array
    {
        if ($adminId > 0) {
            $tree = $this->getAdminMenuTree($adminId);
        } else {
            $tree = $this->getTree();
        }
        
        return $this->convertToRouteConfig($tree);
    }

    /**
     * 将菜单树转换为前端路由配置
     * @param array $tree 菜单树
     * @return array
     */
    private function convertToRouteConfig(array $tree): array
    {
        $routes = [];
        
        foreach ($tree as $menu) {
            if ($menu['type'] == self::TYPE_BUTTON) { // 跳过按钮类型
                continue;
            }
            
            $route = [
                'id' => $menu['id'],
                'name' => $menu['name'],
                'path' => $menu['path'],
                'component' => $menu['component'] ?: 'Layout',
                'meta' => [
                    'title' => $menu['title'],
                    'icon' => $menu['icon'],
                    'keepAlive' => $menu['cache'],
                    'isHide' => $menu['hide'],
                    'isHideTab' => $menu['hide_tab'],
                    'fixedTab' => $menu['fixed_tab'],
                    'isFullPage' => $menu['full_page'],
                    'showBadge' => $menu['show_badge'],
                    'showTextBadge' => $menu['badge_text'],
                    'permission' => $menu['permission']
                ]
            ];
            
            // 添加重定向路径
            if (!empty($menu['redirect'])) {
                $route['redirect'] = $menu['redirect'];
            }
            
            // 处理角色权限
            if (!empty($menu['roles'])) {
                $roles = is_string($menu['roles']) ? json_decode($menu['roles'], true) : $menu['roles'];
                if (is_array($roles)) {
                    $route['meta']['roles'] = $roles;
                }
            }
            
            // 处理外链配置
            if (!empty($menu['link'])) {
                $route['meta']['link'] = $menu['link'];
                $route['meta']['isIframe'] = $menu['iframe'];
            }
            
            // 处理徽章文本
            if (!empty($menu['badge_text'])) {
                $route['meta']['showTextBadge'] = $menu['badge_text'];
            }
            
            // 处理权限按钮列表
            if (!empty($menu['auth_list'])) {
                $authList = is_string($menu['auth_list']) ? json_decode($menu['auth_list'], true) : $menu['auth_list'];
                if (is_array($authList)) {
                    $route['meta']['authList'] = $authList;
                }
            }
            
            // 处理激活路径
            if (!empty($menu['active_path'])) {
                $route['meta']['activePath'] = $menu['active_path'];
            }
            
            // 处理子菜单
            if (!empty($menu['children'])) {
                $route['children'] = $this->convertToRouteConfig($menu['children']);
            }
            
            $routes[] = $route;
        }
        
        return $routes;
    }

    /**
     * 数据库数据转换为前端表单数据
     * @param array $menu 菜单数据
     * @return array
     */
    public function toFormData(array $menu): array
    {
        $formData = [
            'id' => $menu['id'] ?? 0,
            'parent_id' => $menu['parent_id'] ?? 0,
            'name' => $menu['name'] ?? '',
            'path' => $menu['path'] ?? '',
            'component' => $menu['component'] ?? '',
            'redirect' => $menu['redirect'] ?? '',
            'title' => $menu['title'] ?? '',
            'icon' => $menu['icon'] ?? '',
            'type' => $menu['type'] ?? self::TYPE_DIRECTORY,
            'permission' => $menu['permission'] ?? '',
            'hide' => $menu['hide'] ?? false,
            'keepAlive' => $menu['keepAlive'] ?? true,
            'fixed_tab' => $menu['fixed_tab'] ?? false,
            'full_page' => $menu['full_page'] ?? false,
            'link' => $menu['link'] ?? '',
            'iframe' => $menu['iframe'] ?? false,
            'show_badge' => $menu['show_badge'] ?? false,
            'badge_text' => $menu['badge_text'] ?? '',
            'active_path' => $menu['active_path'] ?? '',
            'status' => $menu['status'] ?? 1,
            'sort' => $menu['sort'] ?? 100
        ];

        // 处理JSON字段
        if (isset($menu['roles'])) {
            $formData['roles'] = is_string($menu['roles']) ? json_decode($menu['roles'], true) : $menu['roles'];
        } else {
            $formData['roles'] = [];
        }

        if (isset($menu['auth_list'])) {
            $formData['auth_list'] = is_string($menu['auth_list']) ? json_decode($menu['auth_list'], true) : $menu['auth_list'];
        } else {
            $formData['auth_list'] = [];
        }

        return $formData;
    }

    /**
     * 前端表单数据转换为数据库格式
     * @param array $formData 表单数据
     * @return array
     */
    public function fromFormData(array $formData): array
    {
        $dbData = [
            'parent_id' => $formData['parent_id'] ?? 0,
            'name' => $formData['name'] ?? '',
            'path' => $formData['path'] ?? '',
            'component' => $formData['component'] ?? '',
            'redirect' => $formData['redirect'] ?? '',
            'title' => $formData['title'] ?? '',
            'icon' => $formData['icon'] ?? '',
            'type' => $formData['type'] ?? self::TYPE_DIRECTORY,
            'permission' => $formData['permission'] ?? '',
            'hide' => $formData['hide'] ?? false,
            'keepAlive' => $formData['keepAlive'] ?? true,
            'fixed_tab' => $formData['fixed_tab'] ?? false,
            'full_page' => $formData['full_page'] ?? false,
            'link' => $formData['link'] ?? '',
            'iframe' => $formData['iframe'] ?? false,
            'show_badge' => $formData['show_badge'] ?? false,
            'badge_text' => $formData['badge_text'] ?? '',
            'active_path' => $formData['active_path'] ?? '',
            'status' => $formData['status'] ?? 1,
            'sort' => $formData['sort'] ?? 100
        ];

        // 处理JSON字段
        if (isset($formData['roles']) && is_array($formData['roles'])) {
            $dbData['roles'] = $formData['roles'];
        }

        if (isset($formData['auth_list']) && is_array($formData['auth_list'])) {
            $dbData['auth_list'] = $formData['auth_list'];
        }

        return $dbData;
    }

    /**
     * 验证菜单数据完整性
     * @param array $data 菜单数据
     * @return array 验证结果 ['valid' => bool, 'errors' => array]
     */
    public function validateMenuData(array $data): array
    {
        $errors = [];

        // 验证必填字段
        if (empty($data['name'])) {
            $errors[] = '菜单名称不能为空';
        } elseif (!self::validateName($data['name'])) {
            $errors[] = '菜单名称格式不正确';
        }

        if (empty($data['title'])) {
            $errors[] = '菜单标题不能为空';
        } elseif (!self::validateTitle($data['title'])) {
            $errors[] = '菜单标题格式不正确';
        }

        // 验证菜单类型
        if (!self::validateMenuType($data['type'] ?? '')) {
            $errors[] = '菜单类型不正确';
        }

        // 验证路径格式
        if (!empty($data['path']) && !self::validatePath($data['path'])) {
            $errors[] = '路由路径格式不正确';
        }

        // 验证组件路径格式
        if (!empty($data['component']) && !self::validateComponent($data['component'])) {
            $errors[] = '组件路径格式不正确';
        }

        // 验证权限标识格式
        if (!empty($data['permission']) && !self::validatePermission($data['permission'])) {
            $errors[] = '权限标识格式不正确';
        }

        // 验证外链URL格式
        if (!empty($data['link']) && !self::validateUrl($data['link'])) {
            $errors[] = '外链地址格式不正确';
        }

        // 验证角色权限数组
        if (isset($data['roles']) && is_array($data['roles']) && !self::validateRoles($data['roles'])) {
            $errors[] = '角色权限数组格式不正确';
        }

        // 验证权限按钮列表
        if (isset($data['auth_list']) && is_array($data['auth_list']) && !self::validateAuthList($data['auth_list'])) {
            $errors[] = '权限按钮列表格式不正确';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 获取菜单类型选项列表
     * @return array
     */
    public static function getMenuTypeOptions(): array
    {
        return [
            ['value' => self::TYPE_DIRECTORY, 'label' => self::TYPE_MAP[self::TYPE_DIRECTORY]],
            ['value' => self::TYPE_MENU, 'label' => self::TYPE_MAP[self::TYPE_MENU]],
            ['value' => self::TYPE_BUTTON, 'label' => self::TYPE_MAP[self::TYPE_BUTTON]],
            ['value' => self::TYPE_LINK, 'label' => self::TYPE_MAP[self::TYPE_LINK]],
            ['value' => self::TYPE_IFRAME, 'label' => self::TYPE_MAP[self::TYPE_IFRAME]]
        ];
    }

    /**
     * 检查菜单是否为外链菜单
     * @return bool
     */
    public function isExternalLink(): bool
    {
        return !empty($this->link);
    }

    /**
     * 检查菜单是否为内嵌菜单
     * @return bool
     */
    public function isIframe(): bool
    {
        return ($this->isExternalLink() && $this->iframe);
    }

    /**
     * 检查菜单是否为目录类型
     * @return bool
     */
    public function isDirectory(): bool
    {
        return $this->type === self::TYPE_DIRECTORY;
    }

    /**
     * 检查菜单是否为菜单页面类型
     * @return bool
     */
    public function isMenuPage(): bool
    {
        return $this->type === self::TYPE_MENU;
    }

    /**
     * 检查菜单是否为按钮类型
     * @return bool
     */
    public function isButton(): bool
    {
        return $this->type === self::TYPE_BUTTON;
    }

    /**
     * 检查菜单是否显示徽章
     * @return bool
     */
    public function hasBadge(): bool
    {
        return $this->show_badge || !empty($this->badge_text);
    }

    /**
     * 获取菜单的完整配置信息
     * @return array
     */
    public function getFullConfig(): array
    {
        $config = $this->toArray();
        
        // 添加计算属性
        $config['type_text'] = $this->getMenuTypeText();
        $config['is_external_link'] = $this->isExternalLink();
        $config['is_iframe_menu'] = $this->isIframe();
        $config['has_badge'] = $this->hasBadge();
        $config['is_active'] = $this->isActive();
        
        return $config;
    }

    // ==================== 菜单排序和状态管理方法 ====================

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

    /**
     * 更新菜单排序
     * @param int $id 菜单ID
     * @param int $sort 新的排序值
     * @return bool
     */
    public function updateSortById(int $id, int $sort): bool
    {
        return $this->where('id', $id)->update(['sort' => $sort]) !== false;
    }

    /**
     * 交换两个菜单的排序
     * @param int $id1 菜单1的ID
     * @param int $id2 菜单2的ID
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function swapSort(int $id1, int $id2): bool
    {
        $menu1 = $this->find($id1);
        $menu2 = $this->find($id2);
        
        if (!$menu1 || !$menu2) {
            return false;
        }
        
        $this->startTrans();
        try {
            $this->where('id', $id1)->update(['sort' => $menu2->sort]);
            $this->where('id', $id2)->update(['sort' => $menu1->sort]);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * 移动菜单到指定位置
     * @param int $id 菜单ID
     * @param int $targetId 目标菜单ID（移动到此菜单之前）
     * @param int $parentId 新的父菜单ID（可选）
     * @return bool
     */
    public function moveMenu(int $id, int $targetId = 0, int $parentId = null): bool
    {
        $menu = $this->find($id);
        if (!$menu) {
            return false;
        }
        
        // 如果指定了新的父菜单ID，验证父菜单是否存在
        if ($parentId !== null) {
            if ($parentId > 0) {
                $parent = $this->find($parentId);
                if (!$parent) {
                    return false;
                }
                
                // 检查是否会形成循环引用
                if ($this->wouldCreateCircularReference($id, $parentId)) {
                    return false;
                }
            }
        } else {
            $parentId = $menu->parent_id;
        }
        
        DB::beginTransaction();
        try {
            // 获取目标位置的排序值
            $newSort = 100;
            if ($targetId > 0) {
                $targetMenu = $this->find($targetId);
                if ($targetMenu) {
                    $newSort = $targetMenu->sort;
                    
                    // 将目标菜单及其后面的菜单排序值都增加10
                    $this->where('parent_id', $parentId)
                         ->where('sort', '>=', $newSort)
                         ->where('id', '<>', $id)
                         ->increment('sort', 10);
                }
            } else {
                // 移动到最后
                $newSort = $this->getNextSort(['parent_id' => $parentId]);
            }
            
            // 更新菜单的父ID和排序
            $this->where('id', $id)->update([
                'parent_id' => $parentId,
                'sort' => $newSort
            ]);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    /**
     * 重新排序同级菜单
     * @param int $parentId 父菜单ID
     * @param array $menuIds 菜单ID数组（按新的排序）
     * @return bool
     */
    public function reorderSiblings(int $parentId, array $menuIds): bool
    {
        if (empty($menuIds)) {
            return false;
        }
        
        DB::beginTransaction();
        try {
            $sort = 100;
            foreach ($menuIds as $menuId) {
                $this->where('id', $menuId)
                     ->where('parent_id', $parentId)
                     ->update(['sort' => $sort]);
                $sort += 10;
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    /**
     * 启用菜单
     * @param int $id 菜单ID
     * @return bool
     */
    public function enableMenu(int $id): bool
    {
        return $this->where('id', $id)->update(['status' => 1]) !== false;
    }

    /**
     * 禁用菜单
     * @param int $id 菜单ID
     * @return bool
     */
    public function disableMenu(int $id): bool
    {
        return $this->where('id', $id)->update(['status' => 0]) !== false;
    }

    /**
     * 切换菜单状态
     * @param int $id
     * @param string $field
     * @return bool
     */
    public function toggleStatus(int $id, string $field = 'status'): bool
    {
        $menu = $this->find($id);
        if (!$menu) {
            return false;
        }
        
        $newStatus = $menu->status ? 0 : 1;
        return $this->where('id', $id)->update(['status' => $newStatus]) !== false;
    }

    /**
     * 批量启用菜单
     * @param array $ids 菜单ID数组
     * @return bool
     */
    public function batchEnable(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }
        
        return $this->whereIn('id', $ids)->update(['status' => 1]) !== false;
    }

    /**
     * 批量禁用菜单
     * @param array $ids 菜单ID数组
     * @return bool
     */
    public function batchDisable(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }
        
        return $this->whereIn('id', $ids)->update(['status' => 0]) !== false;
    }

    /**
     * 显示菜单（取消隐藏）
     * @param int $id 菜单ID
     * @return bool
     */
    public function showMenu(int $id): bool
    {
        return $this->where('id', $id)->update(['hide' => false]) !== false;
    }

    /**
     * 隐藏菜单
     * @param int $id 菜单ID
     * @return bool
     */
    public function hideMenu(int $id): bool
    {
        return $this->where('id', $id)->update(['hide' => true]) !== false;
    }

    /**
     * 切换菜单显示状态
     * @param int $id 菜单ID
     * @return bool
     */
    public function toggleVisibility(int $id): bool
    {
        $menu = $this->find($id);
        if (!$menu) {
            return false;
        }
        
        $newHide = !$menu->hide;
        return $this->where('id', $id)->update(['hide' => $newHide]) !== false;
    }

    /**
     * 软删除菜单
     * @param int $id 菜单ID
     * @return bool
     */
    public function softDeleteMenu(int $id): bool
    {
        // 检查是否有子菜单
        if ($this->hasChildren($id)) {
            return false;
        }
        
        return $this->where('id', $id)->update(['deleted' => true]) !== false;
    }

    /**
     * 恢复已删除的菜单
     * @param int $id 菜单ID
     * @return bool
     */
    public function restoreMenu(int $id): bool
    {
        return $this->where('id', $id)->update(['deleted' => false]) !== false;
    }

    /**
     * 批量软删除菜单
     * @param array $ids 菜单ID数组
     * @return bool
     */
    public function batchSoftDelete(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }
        
        // 检查是否有子菜单
        foreach ($ids as $id) {
            if ($this->hasChildren($id)) {
                return false;
            }
        }
        
        return $this->whereIn('id', $ids)->update(['deleted' => true]) !== false;
    }

    /**
     * 批量恢复菜单
     * @param array $ids 菜单ID数组
     * @return bool
     */
    public function batchRestore(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }
        
        return $this->whereIn('id', $ids)->update(['deleted' => false]) !== false;
    }

    /**
     * 永久删除菜单（物理删除）
     * @param int $id 菜单ID
     * @return bool
     */
    public function forceDeleteMenu(int $id): bool
    {
        // 检查是否有子菜单
        if ($this->hasChildren($id)) {
            return false;
        }
        
        // 检查是否被角色使用
        if ($this->isUsed($id)) {
            return false;
        }
        
        return $this->where('id', $id)->delete() !== false;
    }

    /**
     * 获取已删除的菜单列表
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return LengthAwarePaginator
     */
    public function getDeletedMenus(int $page = 1, int $limit = 15): LengthAwarePaginator
    {
        return $this->where('deleted', true)
                   ->orderBy('updated_at', 'desc')
                   ->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * 处理拖拽排序数据
     * @param array $dragData 拖拽数据 [['id' => 1, 'parent_id' => 0, 'index' => 0], ...]
     * @return bool
     */
    public function handleDragSort(array $dragData): bool
    {
        if (empty($dragData)) {
            return false;
        }
        
        DB::beginTransaction();
        try {
            // 按父菜单分组处理
            $groupedData = [];
            foreach ($dragData as $item) {
                $parentId = $item['parent_id'] ?? 0;
                if (!isset($groupedData[$parentId])) {
                    $groupedData[$parentId] = [];
                }
                $groupedData[$parentId][] = $item;
            }
            
            // 为每个分组重新排序
            foreach ($groupedData as $parentId => $items) {
                // 按index排序
                usort($items, function($a, $b) {
                    return ($a['index'] ?? 0) - ($b['index'] ?? 0);
                });
                
                // 更新排序和父菜单
                $sort = 100;
                foreach ($items as $item) {
                    if (isset($item['id'])) {
                        $updateData = [
                            'sort' => $sort,
                            'parent_id' => $parentId
                        ];
                        
                        // 检查是否会形成循环引用
                        if ($parentId > 0 && !$this->wouldCreateCircularReference($item['id'], $parentId)) {
                            $this->where('id', $item['id'])->update($updateData);
                        } elseif ($parentId == 0) {
                            $this->where('id', $item['id'])->update($updateData);
                        }
                        
                        $sort += 10;
                    }
                }
            }
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    /**
     * 获取菜单排序统计信息
     * @param int $parentId 父菜单ID
     * @return array
     */
    public function getSortStats(int $parentId = 0): array
    {
        $menus = $this->where('parent_id', $parentId)
                     ->where('deleted', false)
                     ->orderBy('sort', 'asc')
                     ->get();
        
        $stats = [
            'total' => $menus->count(),
            'min_sort' => 0,
            'max_sort' => 0,
            'gaps' => [],
            'duplicates' => []
        ];
        
        if ($menus->count() > 0) {
            $sorts = $menus->pluck('sort')->toArray();
            $stats['min_sort'] = min($sorts);
            $stats['max_sort'] = max($sorts);
            
            // 检查重复的排序值
            $sortCounts = array_count_values($sorts);
            foreach ($sortCounts as $sort => $count) {
                if ($count > 1) {
                    $stats['duplicates'][] = $sort;
                }
            }
            
            // 检查排序间隙
            sort($sorts);
            for ($i = 1; $i < count($sorts); $i++) {
                $gap = $sorts[$i] - $sorts[$i-1];
                if ($gap > 10) {
                    $stats['gaps'][] = [
                        'from' => $sorts[$i-1],
                        'to' => $sorts[$i],
                        'gap' => $gap
                    ];
                }
            }
        }
        
        return $stats;
    }

    /**
     * 修复菜单排序（重新分配排序值）
     * @param int $parentId 父菜单ID
     * @return bool
     */
    public function fixSort(int $parentId = 0): bool
    {
        $menus = $this->where('parent_id', $parentId)
                     ->where('deleted', false)
                     ->orderBy('sort', 'asc')
                     ->orderBy('id', 'asc')
                     ->get();
        
        if ($menus->count() == 0) {
            return true;
        }
        
        DB::beginTransaction();
        try {
            $sort = 100;
            foreach ($menus as $menu) {
                $this->where('id', $menu->id)->update(['sort' => $sort]);
                $sort += 10;
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    /**
     * 检查菜单是否可以移动到指定位置
     * @param int $menuId 菜单ID
     * @param int $targetParentId 目标父菜单ID
     * @return array 检查结果 ['can_move' => bool, 'reason' => string]
     */
    public function canMoveTo(int $menuId, int $targetParentId): array
    {
        // 不能移动到自己
        if ($menuId == $targetParentId) {
            return ['can_move' => false, 'reason' => '不能将菜单移动到自己'];
        }
        
        // 检查目标父菜单是否存在
        if ($targetParentId > 0) {
            $targetParent = $this->find($targetParentId);
            if (!$targetParent) {
                return ['can_move' => false, 'reason' => '目标父菜单不存在'];
            }
        }
        
        // 检查是否会形成循环引用
        if ($this->wouldCreateCircularReference($menuId, $targetParentId)) {
            return ['can_move' => false, 'reason' => '移动会形成循环引用'];
        }
        
        return ['can_move' => true, 'reason' => ''];
    }

    /**
     * 获取菜单状态统计
     * @return array
     */
    public function getStatusStats(): array
    {
        return [
            'total' => $this->where('deleted', false)->count(),
            'enabled' => $this->where('deleted', false)->where('status', 1)->count(),
            'disabled' => $this->where('deleted', false)->where('status', 0)->count(),
            'hide' => $this->where('deleted', false)->where('hide', true)->count(),
            'visible' => $this->where('deleted', false)->where('hide', false)->count(),
            'deleted' => $this->where('deleted', true)->count()
        ];
    }
}