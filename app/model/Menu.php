<?php

namespace plugin\theadmin\app\model;

use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;
use think\model\relation\HasOne;
use think\Paginator;

/**
 * 菜单模型
 * @property mixed $roles
 * @property mixed $parent
 * @property mixed $children
 */
class Menu extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $name = 'sys_menu';

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
        'parent_id' => 'integer',
        'menu_type' => 'integer',
        'is_hidden' => 'boolean',
        'is_cache' => 'boolean',
        'is_affix' => 'boolean',
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
        return $this->hasMany(self::class, 'parent_id', 'id')->order('sort asc, id asc');
    }

    /**
     * 获取菜单树形结构
     * @param int $parentId 父菜单ID
     * @param bool $onlyEnabled 是否只获取启用的菜单
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getTree(int $parentId = 0, bool $onlyEnabled = true): array
    {
        $query = $this->where('parent_id', $parentId);
        
        if ($onlyEnabled) {
            $query->enabled();
        }
        
        $menus = $query->order('sort asc, id asc')->select();
        $tree = [];
        
        foreach ($menus as $menu) {
            $item = $menu->toArray();
            $item['children'] = $this->getTree($menu->id, $onlyEnabled);
            $tree[] = $item;
        }
        
        return $tree;
    }

    /**
     * 获取管理员菜单树
     * @param int $adminId 管理员ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAdminMenuTree(int $adminId): array
    {
        $admin = Admin::find($adminId);
        if (!$admin) {
            return [];
        }
        
        // 获取管理员的所有菜单ID
        $menuIds = [];
        $roles = $admin->roles()->with('menus')->select();
        
        foreach ($roles as $role) {
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
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function buildTreeFromIds(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }
        
        // 获取所有相关菜单
        $menus = $this->whereIn('id', $menuIds)
                     ->enabled()
                     ->order('sort asc, id asc')
                     ->select();
        
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
     * @return Paginator
     * @throws DbException
     */
    public function getListWithLevel(array $where = [], int $page = 1, int $limit = 15): Paginator
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
        
        return $query->order('sort asc, id asc')->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
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
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function updateMenu(int $id, array $data): bool
    {
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
        
        return $this->where('id', $id)->update($data) !== false;
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
        
        $this->startTrans();
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
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
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
        // 菜单名称只能包含字母、数字、下划线、中划线、中文字符，长度2-50
        return preg_match('/^[a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]{2,50}$/u', $name);
    }

    /**
     * 验证菜单标题格式
     * @param string $title 菜单标题
     * @return bool
     */
    public static function validateTitle(string $title): bool
    {
        // 菜单标题长度2-50，不能为空
        return !empty(trim($title)) && mb_strlen(trim($title)) >= 2 && mb_strlen(trim($title)) <= 50;
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
        
        // 路径必须以/开头，只能包含字母、数字、下划线、中划线、斜杠
        return preg_match('/^\/[a-zA-Z0-9_\-\/]*$/', $path);
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
        $types = [
            1 => '目录',
            2 => '菜单',
            3 => '按钮'
        ];
        
        return $types[$this->menu_type] ?? '未知';
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
     * @return Collection
     */
    public function getTopLevelMenus(bool $onlyEnabled = true): Collection
    {
        $query = $this->where('parent_id', 0);
        
        if ($onlyEnabled) {
            $query->enabled();
        }
        
        return $query->order('sort asc, id asc')->select();
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
        $children = $this->where('parent_id', $menuId)->select();
        
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
                   ->order('sort asc, id asc')
                   ->select();
    }

    /**
     * 获取启用的菜单列表（用于下拉选择）
     * @return Collection
     */
    public function getEnabledList(): Collection
    {
        return $this->enabled()->order('sort asc, id asc')->select();
    }

    /**
     * 构建前端路由配置
     * @param int $adminId 管理员ID（可选）
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
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
            if ($menu['menu_type'] == 3) { // 跳过按钮类型
                continue;
            }
            
            $route = [
                'id' => $menu['id'],
                'name' => $menu['name'],
                'path' => $menu['path'],
                'component' => $menu['component'],
                'redirect' => $menu['redirect'],
                'meta' => [
                    'title' => $menu['title'],
                    'icon' => $menu['icon'],
                    'hidden' => $menu['is_hidden'],
                    'cache' => $menu['is_cache'],
                    'affix' => $menu['is_affix'],
                    'permission' => $menu['permission']
                ]
            ];
            
            if (!empty($menu['children'])) {
                $route['children'] = $this->convertToRouteConfig($menu['children']);
            }
            
            $routes[] = $route;
        }
        
        return $routes;
    }
}