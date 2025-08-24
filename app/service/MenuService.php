<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\Menu;
use think\Paginator;

/**
 * 菜单服务类
 */
class MenuService
{
    /**
     * 获取菜单列表
     * @param array $params 查询参数
     * @return Paginator
     */
    public function getMenuList(array $params = []): Paginator
    {
        $menuModel = ModelFactory::menu();
        
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
        if (!empty($params['title'])) {
            $searchParams['title'] = $params['title'];
        }
        
        return $menuModel->getListWithLevel(array_merge($where, $searchParams), $page, $limit);
    }

    /**
     * 获取菜单树形结构
     * @param int $parentId 父菜单ID
     * @param bool $onlyEnabled 是否只获取启用的菜单
     * @return array
     */
    public function getMenuTree(int $parentId = 0, bool $onlyEnabled = true): array
    {
        $menuModel = ModelFactory::menu();
        return $menuModel->getTree($parentId, $onlyEnabled);
    }

    /**
     * 根据ID获取菜单详情
     * @param int $id 菜单ID
     * @return Menu
     * @throws ApiException
     */
    public function getMenuById(int $id): Menu
    {
        $menuModel = ModelFactory::menu();
        $menu = $menuModel->with(['parent', 'children', 'roles'])->find($id);
        
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $menu;
    }

    /**
     * 创建菜单
     * @param array $data 菜单数据
     * @return Menu
     * @throws ApiException
     */
    public function createMenu(array $data): Menu
    {
        // 数据验证
        $this->validateMenuData($data, true);
        
        $menuModel = ModelFactory::menu();
        
        // 验证父菜单是否存在
        if (!empty($data['parent_id']) && $data['parent_id'] > 0) {
            $parent = $menuModel->find($data['parent_id']);
            if (!$parent) {
                throw new ApiException(Code::MENU_NOT_FOUND, '父菜单不存在');
            }
        }
        
        // 设置默认值
        $data['status'] = $data['status'] ?? true;
        $data['deleted'] = false;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // 设置排序值
        if (!isset($data['sort'])) {
            $data['sort'] = $this->getNextSort($data['parent_id'] ?? 0);
        }
        
        // 创建菜单
        $menu = $menuModel->createMenu($data);
        
        if (!$menu) {
            throw new ApiException(Code::SYSTEM_ERROR, '创建菜单失败');
        }
        
        return $menu;
    }

    /**
     * 更新菜单
     * @param int $id 菜单ID
     * @param array $data 更新数据
     * @return bool
     * @throws ApiException
     */
    public function updateMenu(int $id, array $data): bool
    {
        // 数据验证
        $this->validateMenuData($data, false);
        
        $menuModel = ModelFactory::menu();
        
        // 检查菜单是否存在
        $menu = $menuModel->find($id);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        // 验证父菜单
        if (isset($data['parent_id'])) {
            if ($data['parent_id'] > 0) {
                // 不能将自己设为父菜单
                if ($data['parent_id'] == $id) {
                    throw new ApiException(Code::PARAMETER_ERROR, '不能将自己设为父菜单');
                }
                
                // 检查父菜单是否存在
                $parent = $menuModel->find($data['parent_id']);
                if (!$parent) {
                    throw new ApiException(Code::MENU_NOT_FOUND, '父菜单不存在');
                }
                
                // 检查是否会形成循环引用
                if ($this->wouldCreateCircularReference($id, $data['parent_id'])) {
                    throw new ApiException(Code::PARAMETER_ERROR, '不能形成循环引用');
                }
            }
        }
        
        // 更新时间
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // 更新菜单
        $result = $menuModel->updateMenu($id, $data);
        
        if (!$result) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新菜单失败');
        }
        
        return true;
    }

    /**
     * 删除菜单
     * @param int $id 菜单ID
     * @return bool
     * @throws ApiException
     */
    public function deleteMenu(int $id): bool
    {
        $menuModel = ModelFactory::menu();
        
        // 检查菜单是否存在
        $menu = $menuModel->find($id);
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
        $result = $menuModel->destroy($id);
        
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
        $menuModel = ModelFactory::menu();
        
        // 检查菜单是否存在
        $menu = $menuModel->find($id);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        // 更新状态
        $result = $menuModel->where('id', $id)->update([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
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
        $menuModel = ModelFactory::menu();
        return $menuModel->getAdminMenuTree($adminId);
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
        
        $menuModel = ModelFactory::menu();
        
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
        $result = $menuModel->batchUpdateSort($sortData);
        
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
        $menuModel = ModelFactory::menu();
        
        // 检查菜单是否存在
        $menu = $menuModel->find($menuId);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $menuModel->getMenuPath($menuId);
    }

    /**
     * 获取菜单深度
     * @param int $menuId 菜单ID
     * @return int
     * @throws ApiException
     */
    public function getMenuDepth(int $menuId): int
    {
        $menuModel = ModelFactory::menu();
        
        // 检查菜单是否存在
        $menu = $menuModel->find($menuId);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $menuModel->getMenuDepth($menuId);
    }

    /**
     * 获取所有顶级菜单
     * @param bool $onlyEnabled 是否只获取启用的菜单
     * @return array
     */
    public function getTopLevelMenus(bool $onlyEnabled = true): array
    {
        $menuModel = ModelFactory::menu();
        $menus = $menuModel->getTopLevelMenus($onlyEnabled);
        
        $result = [];
        foreach ($menus as $menu) {
            $result[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'title' => $menu->title,
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
        $menuModel = ModelFactory::menu();
        
        // 检查菜单是否存在
        $menu = $menuModel->find($menuId);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $menuModel->getDescendantIds($menuId);
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
        $menuModel = ModelFactory::menu();
        
        // 检查菜单是否存在
        $menu = $menuModel->find($menuId);
        if (!$menu) {
            throw new ApiException(Code::MENU_NOT_FOUND, '菜单不存在');
        }
        
        return $menuModel->getFullPath($menuId, $separator);
    }

    /**
     * 构建前端路由配置
     * @param int $adminId 管理员ID（可选）
     * @return array
     */
    public function buildRouteConfig(int $adminId = 0): array
    {
        $menuModel = ModelFactory::menu();
        return $menuModel->buildRouteConfig($adminId);
    }

    /**
     * 根据权限标识获取菜单
     * @param string $permission 权限标识
     * @return array
     */
    public function getMenusByPermission(string $permission): array
    {
        $menuModel = ModelFactory::menu();
        $menus = $menuModel->getByPermission($permission);
        
        $result = [];
        foreach ($menus as $menu) {
            $result[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'title' => $menu->title,
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
        $menuModel = ModelFactory::menu();
        $menus = $menuModel->getEnabledList();
        
        $result = [];
        foreach ($menus as $menu) {
            $result[] = [
                'id' => $menu->id,
                'name' => $menu->name,
                'title' => $menu->title,
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
        $menuModel = ModelFactory::menu();
        
        // 检查菜单是否存在
        $menu = $menuModel->find($menuId);
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
            $parent = $menuModel->find($parentId);
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
        
        // 更新菜单层级
        $result = $menuModel->where('id', $menuId)->update([
            'parent_id' => $parentId,
            'sort' => $sort,
            'updated_at' => date('Y-m-d H:i:s')
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
        // 创建时必须提供菜单名称和标题
        if ($isCreate) {
            if (empty($data['name'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单名称不能为空');
            }
            if (empty($data['title'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单标题不能为空');
            }
            if (!isset($data['menu_type'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单类型不能为空');
            }
        }
        
        // 菜单名称格式验证
        if (!empty($data['name'])) {
            if (!Menu::validateName($data['name'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单名称格式不正确，只能包含字母、数字、下划线、中划线、中文字符，长度2-50个字符');
            }
        }
        
        // 菜单标题格式验证
        if (!empty($data['title'])) {
            if (!Menu::validateTitle($data['title'])) {
                throw new ApiException(Code::PARAMETER_ERROR, '菜单标题长度必须在2-50个字符之间');
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
        
        $menuModel = ModelFactory::menu();
        
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
            $parent = $menuModel->find($currentParentId);
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
        $menuModel = ModelFactory::menu();
        $maxSort = $menuModel->where('parent_id', $parentId)->max('sort');
        return ($maxSort ?? 0) + 1;
    }
}