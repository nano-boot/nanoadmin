<?php

namespace plugin\theadmin\app\model;

use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\model\relation\BelongsTo;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;

/**
 * 菜单模型
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
     * 关联父菜单
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 关联子菜单
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->order('sort asc');
    }

    /**
     * 关联角色
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'sys_role_menu', 'role_id', 'menu_id');
    }

    /**
     * 获取菜单树形结构
     * @param array $where 查询条件
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getTree(array $where = []): array
    {
        $menus = $this->where($where)->order('sort asc, id asc')->select();
        
        return $this->buildTree($menus->toArray(), 0);
    }

    /**
     * 构建树形结构
     * @param array $menus 菜单数据
     * @param int $parentId 父级ID
     * @return array
     */
    private function buildTree(array $menus, int $parentId = 0): array
    {
        $tree = [];
        
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $children = $this->buildTree($menus, $menu['id']);
                if (!empty($children)) {
                    $menu['children'] = $children;
                }
                $tree[] = $menu;
            }
        }
        
        return $tree;
    }

    /**
     * 获取菜单列表（平铺结构，带层级标识）
     * @param array $where 查询条件
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getListWithLevel(array $where = []): array
    {
        $tree = $this->getTree($where);
        $list = [];
        
        $this->treeToList($tree, $list, 0);
        
        return $list;
    }

    /**
     * 树形结构转平铺列表
     * @param array $tree 树形数据
     * @param array $list 平铺列表
     * @param int $level 层级
     * @return void
     */
    private function treeToList(array $tree, array &$list, int $level = 0): void
    {
        foreach ($tree as $item) {
            $item['level'] = $level;
            $item['level_name'] = str_repeat('　', $level) . $item['title'];
            
            $children = $item['children'] ?? [];
            unset($item['children']);
            
            $list[] = $item;
            
            if (!empty($children)) {
                $this->treeToList($children, $list, $level + 1);
            }
        }
    }

    /**
     * 创建菜单
     * @param array $data 菜单数据
     * @return static|false
     */
    public function createMenu(array $data): bool|Menu|static
    {
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
        // 检查是否将父级设置为自己或自己的子级
        if (isset($data['parent_id']) && $data['parent_id'] > 0) {
            if ($data['parent_id'] == $id || $this->isChildOf($id, $data['parent_id'])) {
                return false;
            }
        }
        
        return $this->where('id', $id)->update($data) !== false;
    }

    /**
     * 检查是否为子菜单
     * @param int $parentId 父级ID
     * @param int $childId 子级ID
     * @return bool
     */
    private function isChildOf(int $parentId, int $childId): bool
    {
        $children = $this->where('parent_id', $parentId)->column('id');
        
        if (in_array($childId, $children)) {
            return true;
        }
        
        foreach ($children as $id) {
            if ($this->isChildOf($id, $childId)) {
                return true;
            }
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
        $childCount = $this->where('parent_id', $id)->count();
        if ($childCount > 0) {
            return false;
        }
        
        return $this->destroy($id) !== false;
    }

    /**
     * 批量更新菜单排序
     * @param array $data 排序数据
     * @return bool
     */
    public function updateMenuSort(array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        
        $this->startTrans();
        try {
            foreach ($data as $item) {
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
     * 获取用户菜单树（根据角色权限）
     * @param array $roleIds 角色ID数组
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getUserMenuTree(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }
        
        // 获取角色关联的菜单ID
        $menuIds = \think\facade\Db::table('sys_role_menu')
            ->whereIn('role_id', $roleIds)
            ->column('menu_id');
        
        if (empty($menuIds)) {
            return [];
        }
        
        // 获取菜单数据
        $menus = $this->whereIn('id', $menuIds)
            ->where('status', 1)
            ->order('sort asc, id asc')
            ->select();
        
        return $this->buildTree($menus->toArray(), 0);
    }

    /**
     * 获取面包屑导航
     * @param int $menuId 菜单ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getBreadcrumb(int $menuId): array
    {
        $breadcrumb = [];
        $menu = $this->find($menuId);
        
        while ($menu) {
            array_unshift($breadcrumb, [
                'id' => $menu->id,
                'name' => $menu->name,
                'title' => $menu->title,
                'path' => $menu->path
            ]);
            
            $menu = $menu->parent_id > 0 ? $this->find($menu->parent_id) : null;
        }
        
        return $breadcrumb;
    }

    /**
     * 获取菜单类型选项
     * @return array
     */
    public function getMenuTypes(): array
    {
        return [
            ['value' => 1, 'label' => '目录'],
            ['value' => 2, 'label' => '菜单'],
            ['value' => 3, 'label' => '按钮']
        ];
    }

    /**
     * 获取父级菜单选项
     * @param int $excludeId 排除的菜单ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getParentOptions(int $excludeId = 0): array
    {
        $where = ['menu_type' => ['in', [1, 2]]]; // 只有目录和菜单可以作为父级
        
        if ($excludeId > 0) {
            $where['id'] = ['<>', $excludeId];
        }
        
        $list = $this->getListWithLevel($where);
        
        // 添加顶级选项
        array_unshift($list, [
            'id' => 0,
            'title' => '顶级菜单',
            'level_name' => '顶级菜单'
        ]);
        
        return $list;
    }

    /**
     * 检查菜单是否被使用
     * @param int $id 菜单ID
     * @return bool
     */
    public function isUsed(int $id): bool
    {
        // 检查是否有角色使用此菜单
        $roleCount = $this->roles()->where('menu_id', $id)->count();
        
        return $roleCount > 0;
    }
}