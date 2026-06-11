<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\Admin;
use plugin\theadmin\app\model\Menu;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 菜单服务类
 */
class MenuService
{
    /**
     * 菜单模型实例
     * @var Menu
     */
    private Menu $model;

    /**
     * 管理员模型实例
     * @var Admin
     */
    private Admin $adminModel;

    /**
     * 构造函数
     * @param Menu $model 菜单模型实例
     */
    public function __construct(Menu $model)
    {
        $this->model = $model;
        $this->adminModel = new Admin();
    }
    /**
     * 获取菜单列表
     * @param array $params 查询参数
     * @return LengthAwarePaginator
     */
    public function getMenuList(array $params = []): LengthAwarePaginator
    {
        // 构建查询条件
        $where = [];
        
        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $where['status'] = (bool)$params['status'];
        }
        
        // 菜单类型筛选
        if (isset($params['menu_type']) && $params['menu_type'] !== '') {
            $where['menu_type'] = (int)$params['menu_type'];
        }
        
        // 排除已删除的记录
        $where['deleted'] = false;
        
        // 分页参数
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 15;
        
        // 搜索条件
        $searchParams = [];
        if (!empty($params['name'])) {
            $searchParams['name'] = $params['name'];
        }
        
        return $this->model->getListWithLevel(array_merge($where, $searchParams), $page, $limit);
    }

    /**
     * 获取菜单树形结构
     * @param int $parentId 父菜单ID
     * @param bool $onlyEnabled 是否只获取启用的菜单
     * @return array
     */
    public function getMenuTree(int $parentId = 0, bool $onlyEnabled = true): array
    {
        return $this->model->getTree($parentId, $onlyEnabled);
    }

    /**
     * 根据ID获取菜单详情
     * @param int $id 菜单ID
     * @return Menu
     * @throws ApiException
     */
    public function getMenuById(int $id): Menu
    {
        $menu = $this->model->with(['parent', 'children', 'roles'])->find($id);
        
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $menu;
    }

    /**
     * 创建菜单
     * @param array $data 菜单数据
     * @return array
     * @throws ApiException
     */
    public function createMenu(array $data): array
    {
        // 按钮节点强校验
        $this->validateButtonNode($data);

        // 验证父菜单是否存在（如果不是顶级菜单）
        if (!empty($data['parent_id']) && $data['parent_id'] > 0) {
            $parent = $this->model->find($data['parent_id']);
            if (!$parent) {
                throw new ApiException(Code::MENU_NOT_FOUND, '父菜单不存在');
            }
        }
        
        // 检查同级菜单名称是否重复
        $existingMenu = $this->model
            ->where('parent_id', $data['parent_id'] ?? 0)
            ->where('name', $data['name'])
            ->first();
        
        if ($existingMenu) {
            throw new ApiException(Code::PARAMETER_ERROR, '同级菜单中已存在相同的路由名称');
        }
        
        // 检查路由路径是否重复（仅对菜单类型检查）
        if (isset($data['type']) && $data['type'] === 'M' && !empty($data['path'])) {
            $existingPath = $this->model
                ->where('path', $data['path'])
                ->where('type', 'M')
                ->first();
            
            if ($existingPath) {
                throw new ApiException(Code::PARAMETER_ERROR, '路由路径已存在');
            }
        }
        
        // 创建菜单（默认值由 Model 层处理）
        $menu = $this->model->create($data);
        
        if (!$menu) {
            throw new ApiException(Code::SYSTEM_ERROR, '创建菜单失败');
        }
        
        // 返回格式化后的数据
        $transformService = new MenuTransformService();
        return $transformService->formatForApi($menu->toArray());
    }

    /**
     * 校验按钮节点（B类型）父级业务规则
     * @param array $data 菜单数据
     * @throws ApiException
     */
    private function validateButtonNode(array $data): void
    {
        if (($data['type'] ?? '') !== Menu::TYPE_BUTTON) {
            return;
        }

        $parentId = (int)($data['parent_id'] ?? 0);
        $parent = $this->model->find($parentId);
        if (!$parent) {
            throw new ApiException(Code::MENU_NOT_FOUND, '父级菜单不存在');
        }

        // 父节点必须是菜单页面类型（M），目录类型（D）下挂按钮是配置错误
        if ($parent->type !== Menu::TYPE_MENU) {
            throw new ApiException(
                Code::PARAMETER_ERROR,
                '按钮节点必须挂在「菜单页面」类型的节点下，不能挂在「' . $parent->name . '」（' . Menu::TYPE_MAP[$parent->type] . '）下'
            );
        }
    }

    /**
     * 更新菜单
     * @param array $data 更新数据
     * @return array
     * @throws ApiException
     */
    public function updateMenu(array $data): array
    {
        // 提取菜单ID
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            throw new ApiException(Code::PARAMETER_ERROR, '菜单ID无效');
        }
        
        // 移除ID字段，避免更新时包含ID
        unset($data['id']);

        // 检查菜单是否存在
        $menu = $this->model->find($id);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        // 按钮节点强校验
        $this->validateButtonNode($data);
        
        // 验证父菜单
        if (isset($data['parent_id'])) {
            if ($data['parent_id'] > 0) {
                // 不能将自己设为父菜单
                if ($data['parent_id'] == $id) {
                    throw new ApiException(Code::PARAMETER_ERROR, '不能将自己设为父菜单');
                }
                
                // 检查父菜单是否存在
                $parent = $this->model->find($data['parent_id']);
                if (!$parent) {
                    throw new ApiException(Code::MENU_NOT_FOUND, '父菜单不存在');
                }
                
                // 检查是否会形成循环引用
                if ($this->wouldCreateCircularReference($id, $data['parent_id'])) {
                    throw new ApiException(Code::PARAMETER_ERROR, '不能形成循环引用');
                }
            }
        }
        
        // 检查同级菜单名称是否重复（排除自身）
        if (isset($data['name'])) {
            $existingMenu = $this->model
                ->where('parent_id', $data['parent_id'] ?? $menu->parent_id)
                ->where('name', $data['name'])
                ->where('id', '!=', $id)
                ->first();
            
            if ($existingMenu) {
                throw new ApiException(Code::PARAMETER_ERROR, '同级菜单中已存在相同的路由名称');
            }
        }
        
        // 检查路由路径是否重复（仅对菜单类型检查，排除自身）
        if (isset($data['type']) && $data['type'] === 'M' && !empty($data['path'])) {
            $existingPath = $this->model
                ->where('path', $data['path'])
                ->where('type', 'M')
                ->where('id', '!=', $id)
                ->first();
            
            if ($existingPath) {
                throw new ApiException(Code::PARAMETER_ERROR, message: '路由路径已存在');
            }
        }
        
        // 更新菜单（updated_at 由 Model 层处理）
        $result = $this->model->updateMenu($id, $data);
        
        if (!$result) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新菜单失败');
        }
        
        // 获取更新后的菜单数据
        $updatedMenu = $this->model->find($id);
        
        // 返回格式化后的数据
        $transformService = new MenuTransformService();
        return $transformService->formatForApi($updatedMenu->toArray());
    }

    /**
     * 删除菜单
     * @param int $id 菜单ID
     * @return bool
     * @throws ApiException
     */
    public function deleteMenu(int $id): bool
    {
        // 检查菜单是否存在
        $menu = $this->model->find($id);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        // 检查是否有子菜单
        if ($menu->hasChildren($id)) {
            throw new ApiException(Code::HAS_CHILDREN, '存在子菜单，无法删除');
        }
        
        // 检查是否被角色使用
        if ($menu->isUsed($id)) {
            throw new ApiException(Code::DATA_IN_USE, '菜单正在使用中，无法删除');
        }
        
        // 软删除
        $result = $this->model->destroy($id);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '删除菜单失败');
        }
        
        return true;
    }

    /**
     * 启用/禁用菜单
     * @param int $id 菜单ID
     * @param bool $status 状态
     * @return bool
     * @throws ApiException
     */
    public function toggleMenuStatus(int $id, bool $status): bool
    {
        // 检查菜单是否存在
        $menu = $this->model->find($id);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        // 更新状态（updated_at 由 Model 层自动处理）
        $result = $this->model->where('id', $id)->update(['status' => $status]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新菜单状态失败');
        }
        
        return true;
    }

    /**
     * 获取管理员菜单树
     * @param int $adminId 管理员ID
     * @return array
     */
    public function getAdminMenuTree(int $adminId): array
    {
        return $this->model->getAdminMenuTree($adminId);
    }

    /**
     * 获取管理员可访问的路由配置
     * @param int $adminId 管理员ID
     * @return array
     * @throws ApiException
     */
    public function getAdminRoutes(int $adminId): array
    {
        $menuTree = $this->model->getAdminMenuTree($adminId);
        if (empty($menuTree)) {
            return [];
        }

        $buttonPermissionScope = $this->getAdminButtonPermissionScope($adminId);
        $menuTree = $this->attachRouteAuthList($menuTree, $buttonPermissionScope);

        $transformService = new MenuTransformService();
        return $transformService->toRouteConfigTree($menuTree);
    }

    /**
     * 获取管理员可访问的按钮权限范围
     * @param int $adminId 管理员ID
     * @return array{allowAll:bool,codes:array<int,string>}
     */
    private function getAdminButtonPermissionScope(int $adminId): array
    {
        $admin = $this->adminModel->with(['roles.permissions'])->find($adminId);
        if (!$admin) {
            return [
                'allowAll' => false,
                'codes' => [],
            ];
        }

        $isSuperAdmin = $admin->roles->contains('code', 'R_SUPER');
        if ($isSuperAdmin) {
            return [
                'allowAll' => true,
                'codes' => [],
            ];
        }

        $permissions = [];
        foreach ($admin->roles as $role) {
            if (!$role->isActive() || !isset($role->permissions)) {
                continue;
            }

            foreach ($role->permissions as $permission) {
                $permissionCode = trim((string)($permission->code ?? ''));
                if ($permissionCode !== '') {
                    $permissions[$permissionCode] = true;
                }
            }
        }

        return [
            'allowAll' => false,
            'codes' => array_keys($permissions),
        ];
    }

    /**
     * 为路由树中的页面节点挂载用户级 auth_list
     * @param array $menuTree
     * @param array{allowAll:bool,codes:array<int,string>} $buttonPermissionScope
     * @return array
     */
    private function attachRouteAuthList(array $menuTree, array $buttonPermissionScope): array
    {
        $permissionMap = array_fill_keys($buttonPermissionScope['codes'], true);

        return array_values(array_map(function (array $menu) use ($buttonPermissionScope, $permissionMap) {
            return $this->attachRouteAuthListToNode($menu, $buttonPermissionScope['allowAll'], $permissionMap);
        }, $menuTree));
    }

    /**
     * 递归处理单个菜单节点的 auth_list
     * @param array $menu
     * @param bool $allowAllButtons
     * @param array $permissionMap
     * @return array
     */
    private function attachRouteAuthListToNode(array $menu, bool $allowAllButtons, array $permissionMap): array
    {
        $children = $menu['children'] ?? [];
        $routeChildren = [];
        $buttonChildren = [];

        foreach ($children as $child) {
            if (($child['type'] ?? null) === Menu::TYPE_BUTTON) {
                $buttonChildren[] = $child;
                continue;
            }

            $routeChildren[] = $this->attachRouteAuthListToNode($child, $allowAllButtons, $permissionMap);
        }

        $menu['children'] = $routeChildren;
        $menu['auth_list'] = $this->buildAuthListFromButtons($buttonChildren, $allowAllButtons, $permissionMap);

        return $menu;
    }

    /**
     * 将按钮菜单节点转换为前端 auth_list
     * @param array $buttonChildren
     * @param bool $allowAllButtons
     * @param array $permissionMap
     * @return array
     */
    private function buildAuthListFromButtons(array $buttonChildren, bool $allowAllButtons, array $permissionMap): array
    {
        $authList = [];

        foreach ($buttonChildren as $button) {
            $authMark = trim((string)($button['permission'] ?? ''));
            if ($authMark === '') {
                continue;
            }

            if (!$allowAllButtons && !isset($permissionMap[$authMark])) {
                continue;
            }

            $authList[] = [
                'title' => trim((string)($button['title'] ?? '')),
                'authMark' => $authMark,
            ];
        }

        return $authList;
    }

    /**
     * 批量更新菜单排序
     * @param array $sortData 排序数据
     * @return bool
     * @throws ApiException
     */
    public function batchUpdateSort(array $sortData): bool
    {
        if (empty($sortData)) {
            throw new ApiException(Code::PARAMETER_ERROR, '排序数据不能为空');
        }
        
        // 验证数据格式
        foreach ($sortData as $item) {
            if (!isset($item['id']) || !is_numeric($item['id'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单ID格式不正确');
            }
            
            if (isset($item['sort']) && (!is_numeric($item['sort']) || $item['sort'] < 0)) {
                throw new ApiException(Code::PARAMETER_ERROR, '排序值必须为非负整数');
            }
            
            if (isset($item['parent_id']) && (!is_numeric($item['parent_id']) || $item['parent_id'] < 0)) {
                throw new ApiException(Code::PARAMETER_ERROR, '父菜单ID必须为非负整数');
            }
        }
        
        // 批量更新
        $result = $this->model->batchUpdateSort($sortData);
        
        if (!$result) {
            throw new ApiException(Code::SYSTEM_ERROR, '批量更新排序失败');
        }
        
        return true;
    }

    /**
     * 获取菜单路径（面包屑）
     * @param int $menuId 菜单ID
     * @return array
     * @throws ApiException
     */
    public function getMenuPath(int $menuId): array
    {
        // 检查菜单是否存在
        $menu = $this->model->find($menuId);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $this->model->getMenuPath($menuId);
    }

    /**
     * 获取菜单深度
     * @param int $menuId 菜单ID
     * @return int
     * @throws ApiException
     */
    public function getMenuDepth(int $menuId): int
    {
        // 检查菜单是否存在
        $menu = $this->model->find($menuId);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $this->model->getMenuDepth($menuId);
    }

    /**
     * 获取所有顶级菜单
     * @param bool $onlyEnabled 是否只获取启用的菜单
     * @return array
     */
    public function getTopLevelMenus(bool $onlyEnabled = true): array
    {
        $menus = $this->model->getTopLevelMenus($onlyEnabled);
        
        $result = [];
        foreach ($menus as $menu) {
            $result[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'path' => $menu->path,
                'icon' => $menu->icon,
                'sort' => $menu->sort,
                'menu_type' => $menu->menu_type,
                'menu_type_text' => $menu->getMenuTypeText()
            ];
        }
        
        return $result;
    }

    /**
     * 获取指定菜单的所有子孙菜单ID
     * @param int $menuId 菜单ID
     * @return array
     * @throws ApiException
     */
    public function getDescendantIds(int $menuId): array
    {
        // 检查菜单是否存在
        $menu = $this->model->find($menuId);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $this->model->getDescendantIds($menuId);
    }

    /**
     * 获取菜单的完整路径字符串
     * @param int $menuId 菜单ID
     * @param string $separator 分隔符
     * @return string
     * @throws ApiException
     */
    public function getFullPath(int $menuId, string $separator = ' > '): string
    {
        // 检查菜单是否存在
        $menu = $this->model->find($menuId);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $this->model->getFullPath($menuId, $separator);
    }

    /**
     * 构建前端路由配置
     * @param int $adminId 管理员ID（可选）
     * @return array
     */
    public function buildRouteConfig(int $adminId = 0): array
    {
        return $this->model->buildRouteConfig($adminId);
    }

    /**
     * 根据权限标识获取菜单
     * @param string $permission 权限标识
     * @return array
     */
    public function getMenusByPermission(string $permission): array
    {
        $menus = $this->model->getByPermission($permission);
        
        $result = [];
        foreach ($menus as $menu) {
            $result[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'path' => $menu->path,
                'permission' => $menu->permission
            ];
        }
        
        return $result;
    }

    /**
     * 获取启用的菜单列表（用于下拉选择）
     * @return array
     */
    public function getEnabledMenus(): array
    {
        $menus = $this->model->getEnabledList();
        
        $result = [];
        foreach ($menus as $menu) {
            $result[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'path' => $menu->path,
                'parent_id' => $menu->parent_id,
                'menu_type' => $menu->menu_type,
                'menu_type_text' => $menu->getMenuTypeText(),
                'sort' => $menu->sort
            ];
        }
        
        return $result;
    }

    /**
     * 调整菜单层级
     * @param int $menuId 菜单ID
     * @param int $parentId 新的父菜单ID
     * @param int $sort 排序值
     * @return bool
     * @throws ApiException
     */
    public function adjustMenuLevel(int $menuId, int $parentId, int $sort = 0): bool
    {
        // 检查菜单是否存在
        $menu = $this->model->find($menuId);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        // 验证父菜单
        if ($parentId > 0) {
            // 不能将自己设为父菜单
            if ($parentId == $menuId) {
                throw new ApiException(Code::PARAMETER_ERROR, '不能将自己设为父菜单');
            }
            
            // 检查父菜单是否存在
            $parent = $this->model->find($parentId);
            if (!$parent) {
                throw new ApiException(Code::MENU_NOT_FOUND, '父菜单不存在');
            }
            
            // 检查是否会形成循环引用
            if ($this->wouldCreateCircularReference($menuId, $parentId)) {
                throw new ApiException(Code::PARAMETER_ERROR, '不能形成循环引用');
            }
        }
        
        // 如果没有指定排序值，使用下一个排序值
        if ($sort <= 0) {
            $sort = $this->getNextSort($parentId);
        }
        
        // 更新菜单层级（updated_at 由 Model 层自动处理）
        $result = $this->model->where('id', $menuId)->update([
            'parent_id' => $parentId,
            'sort' => $sort
        ]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '调整菜单层级失败');
        }
        
        return true;
    }

    /**
     * 验证菜单数据
     * @param array $data 菜单数据
     * @param bool $isCreate 是否为创建操作
     * @throws ApiException
     */
    private function validateMenuData(array $data, bool $isCreate = false): void
    {
        // 创建时必须提供菜单名称和菜单类型
        if ($isCreate) {
            if (empty($data['name'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单名称不能为空');
            }
            if (!isset($data['menu_type'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单类型不能为空');
            }
        }
        
        // 菜单名称格式验证
        if (!empty($data['name'])) {
            if (!Menu::validateName($data['name'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单名称格式不正确，长度2-100个字符');
            }
        }
        
        // 路由路径格式验证
        if (isset($data['path'])) {
            if (!Menu::validatePath($data['path'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '路由路径格式不正确，必须以/开头，只能包含字母、数字、下划线、中划线、斜杠');
            }
        }
        
        // 菜单类型验证
        if (isset($data['menu_type'])) {
            if (!in_array($data['menu_type'], [1, 2, 3])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单类型必须为1（目录）、2（菜单）或3（按钮）');
            }
        }
        
        // 父菜单ID验证
        if (isset($data['parent_id'])) {
            if (!is_numeric($data['parent_id']) || $data['parent_id'] < 0) {
                throw new ApiException(Code::PARAMETER_ERROR, '父菜单ID必须为非负整数');
            }
        }
        
        // 排序值验证
        if (isset($data['sort'])) {
            if (!is_numeric($data['sort']) || $data['sort'] < 0) {
                throw new ApiException(Code::PARAMETER_ERROR, '排序值必须为非负整数');
            }
        }
        
        // 组件路径长度验证
        if (!empty($data['component'])) {
            if (mb_strlen($data['component']) > 200) {
                throw new ApiException(Code::PARAMETER_ERROR, '组件路径长度不能超过200个字符');
            }
        }
        
        // 图标长度验证
        if (!empty($data['icon'])) {
            if (mb_strlen($data['icon']) > 100) {
                throw new ApiException(Code::PARAMETER_ERROR, '图标长度不能超过100个字符');
            }
        }
        
        // 权限标识长度验证
        if (!empty($data['permission'])) {
            if (mb_strlen($data['permission']) > 100) {
                throw new ApiException(Code::PARAMETER_ERROR, '权限标识长度不能超过100个字符');
            }
        }
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
            $parent = $this->model->find($currentParentId);
            $currentParentId = $parent ? $parent->parent_id : 0;
        }
        
        return false;
    }

    /**
     * 获取下一个排序值
     * @param int $parentId 父菜单ID
     * @return int
     */
    private function getNextSort(int $parentId = 0): int
    {
        $maxSort = $this->model->where('parent_id', $parentId)->max('sort');
        return ($maxSort ?? 0) + 1;
    }
}