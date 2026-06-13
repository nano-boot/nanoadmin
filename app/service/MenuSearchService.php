<?php

namespace plugin\nanoadmin\app\service;

use plugin\nanoadmin\app\model\Menu;
use Illuminate\Database\Eloquent\Collection;

/**
 * 菜单搜索和过滤服务
 * 实现菜单的搜索、过滤功能，保持层级结构
 */
class MenuSearchService
{
    /**
     * 菜单模型实例
     * @var Menu
     */
    private Menu $menuModel;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->menuModel = new Menu();
    }

    /**
     * 搜索菜单（支持名称和路径模糊搜索）
     * @param string $keyword 搜索关键词
     * @param array $options 搜索选项
     * @return array
     */
    public function searchMenus(string $keyword, array $options = []): array
    {
        // 默认搜索选项
        $defaultOptions = [
            'search_fields' => ['name', 'path'], // 搜索字段
            'include_disabled' => false,                   // 是否包含禁用菜单
            'include_hidden' => false,                     // 是否包含隐藏菜单
            'menu_types' => [],                           // 菜单类型过滤
            'maintain_hierarchy' => true,                 // 是否保持层级结构
            'parent_id' => null                           // 指定父菜单ID
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        if (empty(trim($keyword))) {
            return $this->getAllMenusWithFilters($options);
        }

        // 获取所有匹配的菜单
        $matchedMenus = $this->findMatchingMenus($keyword, $options);
        
        if ($options['maintain_hierarchy']) {
            // 保持层级结构
            return $this->buildHierarchicalResults($matchedMenus, $options);
        } else {
            // 返回平铺结构
            return $this->formatFlatResults($matchedMenus);
        }
    }

    /**
     * 过滤菜单（按状态、类型等条件）
     * @param array $filters 过滤条件
     * @return array
     */
    public function filterMenus(array $filters = []): array
    {
        // 默认过滤条件
        $defaultFilters = [
            'status' => null,           // 菜单状态：true(启用), false(禁用), null(全部)
            'hidden' => null,           // 隐藏状态：true(隐藏), false(显示), null(全部)
            'menu_types' => [],         // 菜单类型数组
            'parent_id' => null,        // 父菜单ID
            'has_children' => null,     // 是否有子菜单：true(有), false(无), null(全部)
            'has_permission' => null,   // 是否有权限标识：true(有), false(无), null(全部)
            'is_external' => null,      // 是否外链：true(是), false(否), null(全部)
            'maintain_hierarchy' => true // 是否保持层级结构
        ];
        
        $filters = array_merge($defaultFilters, $filters);
        
        // 构建查询条件
        $query = $this->menuModel->newQuery();
        
        // 状态过滤
        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }
        
        // 隐藏状态过滤
        if ($filters['hidden'] !== null) {
            $query->where('hidden', $filters['hidden']);
        }
        
        // 菜单类型过滤
        if (!empty($filters['menu_types'])) {
            $query->whereIn('type', $filters['menu_types']);
        }
        
        // 父菜单过滤
        if ($filters['parent_id'] !== null) {
            $query->where('parent_id', $filters['parent_id']);
        }
        
        // 权限标识过滤
        if ($filters['has_permission'] !== null) {
            if ($filters['has_permission']) {
                $query->where('permission', '<>', '');
            } else {
                $query->where('permission', '=', '');
            }
        }
        
        // 外链过滤
        if ($filters['is_external'] !== null) {
            if ($filters['is_external']) {
                $query->where(function($q) {
                    $q->where('type', Menu::TYPE_LINK)
                      ->orWhere('link', '<>', '');
                });
            } else {
                $query->where('type', '<>', Menu::TYPE_LINK)
                      ->where('link', '=', '');
            }
        }
        
        $menus = $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
        
        // 子菜单数量过滤（需要在查询后处理）
        if ($filters['has_children'] !== null) {
            $menus = $menus->filter(function($menu) use ($filters) {
                $hasChildren = $this->menuModel->hasChildren($menu->id);
                return $filters['has_children'] ? $hasChildren : !$hasChildren;
            });
        }
        
        if ($filters['maintain_hierarchy']) {
            return $this->buildTreeFromCollection($menus);
        } else {
            return $this->formatCollectionResults($menus);
        }
    }

    /**
     * 递归搜索菜单（包含子菜单）
     * @param string $keyword 搜索关键词
     * @param int $parentId 父菜单ID
     * @param array $options 搜索选项
     * @return array
     */
    public function recursiveSearch(string $keyword, int $parentId = 0, array $options = []): array
    {
        $results = [];
        
        // 获取当前层级的菜单
        $query = $this->menuModel->where('parent_id', $parentId);
        
        // 应用基础过滤条件
        $this->applyBasicFilters($query, $options);
        
        $menus = $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
        
        /**
         * @var Menu $menu
         */
        foreach ($menus as $menu) {
            $menuArray = $menu->toArray();
            $isMatch = false;
            
            // 检查当前菜单是否匹配
            if ($this->isMenuMatch($menuArray, $keyword, $options['search_fields'] ?? ['name', 'path'])) {
                $isMatch = true;
            }
            
            // 递归搜索子菜单
            $children = $this->recursiveSearch($keyword, $menu->id, $options);
            
            // 如果有匹配的子菜单，当前菜单也应该包含
            if (!empty($children)) {
                $isMatch = true;
                $menuArray['children'] = $children;
            }
            
            // 如果匹配，添加到结果中
            if ($isMatch) {
                if (empty($children)) {
                    $menuArray['children'] = [];
                }
                $results[] = $menuArray;
            }
        }
        
        return $results;
    }

    /**
     * 高级搜索（支持多条件组合）
     *
     * 【性能优化】：原实现对每个匹配菜单调用 getParentMenuIds()，内部 while 循环逐个
     * find() 追溯父链，N 个菜单 × 层级深度 = 大量串行查询。
     * 现改为：BFS 批量查询，每一层只发一次 SELECT，总查询次数 = 树的层数（通常 ≤ 3）。
     *
     * @param array $searchParams 搜索参数
     * @return array
     */
    public function advancedSearch(array $searchParams): array
    {
        // 使用 Menu 模型的标准搜索逻辑（会自动使用配置的搜索字段）
        $query = $this->menuModel->handleSearch($this->menuModel->newQuery(), $searchParams);
        $menus = $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();

        // 是否保持层级结构
        $maintainHierarchy = $searchParams['maintain_hierarchy'] ?? true;

        if ($maintainHierarchy) {
            // 获取所有匹配菜单的ID（用于最终高亮标记）
            $matchedIds = $menus->pluck('id')->toArray();

            // ✅ BFS 批量追溯祖先：每一层只查一次数据库，而非对每个菜单逐个查询
            $loadedIds   = $menus->pluck('id')->flip()->toArray(); // 已加载的 ID 集合（用于去重）
            $pendingIds  = $menus->pluck('parent_id')->filter()->unique()->values()->toArray();

            while (!empty($pendingIds)) {
                // 过滤掉已加载的，只查新的父级
                $pendingIds = array_values(array_diff($pendingIds, array_keys($loadedIds)));
                if (empty($pendingIds)) {
                    break;
                }

                // ✅ 一次 SELECT 拿到这一批父菜单
                $parentMenus = $this->menuModel->whereIn('id', $pendingIds)->get();
                $menus       = $menus->merge($parentMenus);

                // 记录已加载的 ID，准备下一轮
                foreach ($parentMenus as $pm) {
                    $loadedIds[$pm->id] = true;
                }

                // 继续向上追溯（祖父、曾祖父……）
                $pendingIds = $parentMenus->pluck('parent_id')->filter()->unique()->values()->toArray();
            }

            // 构建树形结构，并标记哪些是搜索匹配的
            return $this->buildTreeFromCollection($menus, $matchedIds);
        } else {
            return $this->formatCollectionResults($menus);
        }
    }

    /**
     * 查找匹配的菜单
     * @param string $keyword 搜索关键词
     * @param array $options 搜索选项
     * @return Collection
     */
    private function findMatchingMenus(string $keyword, array $options): Collection
    {
        $query = $this->menuModel->newQuery();
        
        // 构建搜索条件
        $searchFields = $options['search_fields'] ?? ['name', 'path'];
        $query->where(function($q) use ($keyword, $searchFields) {
            foreach ($searchFields as $field) {
                $q->orWhere($field, 'like', '%' . $keyword . '%');
            }
        });
        
        // 应用基础过滤条件
        $this->applyBasicFilters($query, $options);
        
        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
    }

    /**
     * 应用基础过滤条件
     * @param mixed $query 查询构建器
     * @param array $options 选项
     */
    private function applyBasicFilters($query, array $options): void
    {
        // 是否包含禁用菜单
        if (!($options['include_disabled'] ?? false)) {
            $query->where('status', true);
        }
        
        // 是否包含隐藏菜单
        if (!($options['include_hidden'] ?? false)) {
            $query->where('hidden', false);
        }
        
        // 菜单类型过滤
        if (!empty($options['menu_types'])) {
            $query->whereIn('type', $options['menu_types']);
        }
        
        // 父菜单过滤
        if ($options['parent_id'] !== null) {
            $query->where('parent_id', $options['parent_id']);
        }
    }

    /**
     * 构建层级结构的搜索结果
     * @param Collection $matchedMenus 匹配的菜单
     * @param array $options 选项
     * @return array
     */
    private function buildHierarchicalResults(Collection $matchedMenus, array $options): array
    {
        // 获取所有匹配菜单的ID
        $matchedIds = $matchedMenus->pluck('id')->toArray();
        
        // 获取所有相关的父菜单ID
        $allRelevantIds = $this->getAllRelevantMenuIds($matchedIds);
        
        // 获取所有相关菜单
        $query = $this->menuModel->whereIn('id', $allRelevantIds);
        $this->applyBasicFilters($query, $options);
        $allMenus = $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
        
        // 构建树形结构
        return $this->buildTreeFromCollection($allMenus, $matchedIds);
    }

    /**
     * 获取所有相关的菜单ID（包括父菜单）
     * @param array $menuIds 菜单ID数组
     * @return array
     */
    private function getAllRelevantMenuIds(array $menuIds): array
    {
        $allIds = $menuIds;
        
        foreach ($menuIds as $menuId) {
            // 获取父菜单路径
            $parentIds = $this->getParentMenuIds($menuId);
            $allIds = array_merge($allIds, $parentIds);
        }
        
        return array_unique($allIds);
    }

    /**
     * 获取菜单的所有父菜单ID
     * @param int $menuId 菜单ID
     * @return array
     */
    private function getParentMenuIds(int $menuId): array
    {
        $parentIds = [];
        $currentId = $menuId;
        
        while ($currentId > 0) {
            $menu = $this->menuModel->find($currentId);
            if (!$menu || $menu->parent_id == 0) {
                break;
            }
            
            $parentIds[] = $menu->parent_id;
            $currentId = $menu->parent_id;
        }
        
        return $parentIds;
    }

    /**
     * 从集合构建树形结构
     * @param Collection $menus 菜单集合
     * @param array $highlightIds 需要高亮的菜单ID（搜索匹配的）
     * @return array
     */
    private function buildTreeFromCollection(Collection $menus, array $highlightIds = []): array
    {
        // 构建菜单映射
        $menuMap = [];
        foreach ($menus as $menu) {
            $menuArray = $menu->toArray();
            $menuArray['children'] = [];
            $menuArray['is_matched'] = in_array($menu->id, $highlightIds); // 标记是否为搜索匹配项
            $menuMap[$menu->id] = $menuArray;
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
     * 格式化平铺结构的结果
     * @param Collection $menus 菜单集合
     * @return array
     */
    private function formatFlatResults(Collection $menus): array
    {
        $results = [];
        
        foreach ($menus as $menu) {
            $menuArray = $menu->toArray();
            
            // 添加层级信息
            try {
                $menuArray['level'] = $this->menuModel->getMenuDepth($menu->id);
                $menuArray['path_info'] = $this->menuModel->getMenuPath($menu->id);
                $menuArray['full_path'] = $this->menuModel->getFullPath($menu->id);
            } catch (\Exception $e) {
                $menuArray['level'] = 0;
                $menuArray['path_info'] = [];
                $menuArray['full_path'] = $menu->name;
            }
            
            $results[] = $menuArray;
        }
        
        return $results;
    }

    /**
     * 格式化集合结果
     * @param Collection $menus 菜单集合
     * @return array
     */
    private function formatCollectionResults(Collection $menus): array
    {
        return $this->formatFlatResults($menus);
    }

    /**
     * 检查菜单是否匹配搜索条件
     * @param array $menu 菜单数据
     * @param string $keyword 搜索关键词
     * @param array $searchFields 搜索字段
     * @return bool
     */
    private function isMenuMatch(array $menu, string $keyword, array $searchFields): bool
    {
        $keyword = strtolower($keyword);
        
        foreach ($searchFields as $field) {
            if (isset($menu[$field]) && !empty($menu[$field])) {
                if (stripos($menu[$field], $keyword) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * 获取所有菜单（应用过滤条件）
     * @param array $options 选项
     * @return array
     */
    private function getAllMenusWithFilters(array $options): array
    {
        if ($options['maintain_hierarchy']) {
            return $this->recursiveSearch('', $options['parent_id'] ?? 0, $options);
        } else {
            $query = $this->menuModel->newQuery();
            $this->applyBasicFilters($query, $options);
            
            if ($options['parent_id'] !== null) {
                $query->where('parent_id', $options['parent_id']);
            }
            
            $menus = $query->orderBy('sort', 'asc')->orderBy('id', 'asc')->get();
            return $this->formatCollectionResults($menus);
        }
    }

    /**
     * 搜索菜单并返回统计信息
     * @param string $keyword 搜索关键词
     * @param array $options 搜索选项
     * @return array
     */
    public function searchWithStats(string $keyword, array $options = []): array
    {
        $results = $this->searchMenus($keyword, $options);
        
        // 计算统计信息
        $stats = $this->calculateSearchStats($results, $keyword);
        
        return [
            'results' => $results,
            'stats' => $stats,
            'keyword' => $keyword,
            'options' => $options
        ];
    }

    /**
     * 计算搜索统计信息
     * @param array $results 搜索结果
     * @param string $keyword 搜索关键词
     * @return array
     */
    private function calculateSearchStats(array $results, string $keyword): array
    {
        $stats = [
            'total_count' => 0,
            'matched_count' => 0,
            'directory_count' => 0,
            'menu_count' => 0,
            'button_count' => 0,
            'link_count' => 0,
            'iframe_count' => 0,
            'enabled_count' => 0,
            'disabled_count' => 0,
            'hidden_count' => 0,
            'visible_count' => 0
        ];
        
        $this->countMenusRecursively($results, $stats);
        
        return $stats;
    }

    /**
     * 递归统计菜单数量
     * @param array $menus 菜单数组
     * @param array &$stats 统计信息
     */
    private function countMenusRecursively(array $menus, array &$stats): void
    {
        foreach ($menus as $menu) {
            $stats['total_count']++;
            
            // 统计匹配数量
            if ($menu['is_matched'] ?? false) {
                $stats['matched_count']++;
            }
            
            // 按类型统计
            switch ($menu['type']) {
                case Menu::TYPE_DIRECTORY:
                    $stats['directory_count']++;
                    break;
                case Menu::TYPE_MENU:
                    $stats['menu_count']++;
                    break;
                case Menu::TYPE_BUTTON:
                    $stats['button_count']++;
                    break;
                case Menu::TYPE_LINK:
                    $stats['link_count']++;
                    break;
                case Menu::TYPE_IFRAME:
                    $stats['iframe_count']++;
                    break;
            }
            
            // 按状态统计
            if ($menu['status']) {
                $stats['enabled_count']++;
            } else {
                $stats['disabled_count']++;
            }
            
            if ($menu['hidden']) {
                $stats['hidden_count']++;
            } else {
                $stats['visible_count']++;
            }
            
            // 递归统计子菜单
            if (!empty($menu['children'])) {
                $this->countMenusRecursively($menu['children'], $stats);
            }
        }
    }

    /**
     * 获取搜索建议
     * @param string $keyword 搜索关键词
     * @param int $limit 建议数量限制
     * @return array
     */
    public function getSearchSuggestions(string $keyword, int $limit = 10): array
    {
        if (empty(trim($keyword))) {
            return [];
        }
        
        $suggestions = [];
        
        // 菜单名称建议
        $nameMatches = $this->menuModel
            ->where('name', 'like', '%' . $keyword . '%')
            ->where('status', true)
            ->limit($limit)
            ->pluck('name')
            ->toArray();
        
        foreach ($nameMatches as $name) {
            $suggestions[] = [
                'type' => 'name',
                'value' => $name,
                'label' => "菜单名称: {$name}"
            ];
        }
        
        // 路径建议
        $pathMatches = $this->menuModel
            ->where('path', 'like', '%' . $keyword . '%')
            ->where('path', '<>', '')
            ->where('status', true)
            ->limit($limit)
            ->pluck('path')
            ->toArray();
        
        foreach ($pathMatches as $path) {
            $suggestions[] = [
                'type' => 'path',
                'value' => $path,
                'label' => "路由路径: {$path}"
            ];
        }
        
        // 去重并限制数量
        $uniqueSuggestions = [];
        $seen = [];
        
        foreach ($suggestions as $suggestion) {
            $key = $suggestion['type'] . ':' . $suggestion['value'];
            if (!isset($seen[$key]) && count($uniqueSuggestions) < $limit) {
                $seen[$key] = true;
                $uniqueSuggestions[] = $suggestion;
            }
        }
        
        return $uniqueSuggestions;
    }
}