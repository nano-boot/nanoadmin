<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\model\Menu;
use plugin\theadmin\app\model\Admin;
use plugin\theadmin\app\model\Role;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;

/**
 * 菜单权限验证服务
 * 负责处理基于角色的菜单权限验证和过滤
 */
class MenuPermissionService
{
    /**
     * 菜单模型实例
     * @var Menu
     */
    private Menu $menuModel;

    /**
     * 管理员模型实例
     * @var Admin
     */
    private Admin $adminModel;

    /**
     * 角色模型实例
     * @var Role
     */
    private Role $roleModel;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->menuModel = new Menu();
        $this->adminModel = new Admin();
        $this->roleModel = new Role();
    }

    /**
     * 根据管理员角色过滤菜单树
     * @param array $menuTree 完整菜单树
     * @param int $adminId 管理员ID
     * @return array 过滤后的菜单树
     * @throws ApiException
     */
    public function filterMenuTreeByAdmin(array $menuTree, int $adminId): array
    {
        try {
            // 获取管理员信息
            $admin = $this->adminModel->find($adminId);
            if (!$admin) {
                throw new ApiException('管理员不存在', Code::ADMIN_NOT_FOUND);
            }

            // 获取管理员的角色
            $roles = $admin->getRoles();
            if ($roles->isEmpty()) {
                return []; // 没有角色，返回空菜单
            }

            // 获取角色代码列表
            $roleCodes = [];
            foreach ($roles as $role) {
                if ($role->isActive()) {
                    $roleCodes[] = $role->code;
                }
            }

            if (empty($roleCodes)) {
                return []; // 没有激活的角色，返回空菜单
            }

            // 根据角色过滤菜单
            return $this->filterMenuTreeByRoles($menuTree, $roleCodes);
        } catch (\Exception $e) {
            throw new ApiException('菜单权限过滤失败: ' . $e->getMessage(), Code::MENU_PERMISSION_ERROR);
        }
    }

    /**
     * 根据角色代码列表过滤菜单树
     * @param array $menuTree 完整菜单树
     * @param array $roleCodes 角色代码列表
     * @return array 过滤后的菜单树
     */
    public function filterMenuTreeByRoles(array $menuTree, array $roleCodes): array
    {
        $filteredTree = [];

        foreach ($menuTree as $menu) {
            // 检查菜单是否激活
            if (!$this->isMenuActive($menu)) {
                continue;
            }

            // 检查菜单权限
            if ($this->hasMenuPermission($menu, $roleCodes)) {
                $filteredMenu = $menu;

                // 递归过滤子菜单
                if (!empty($menu['children'])) {
                    $filteredChildren = $this->filterMenuTreeByRoles($menu['children'], $roleCodes);
                    $filteredMenu['children'] = $filteredChildren;
                }

                // 过滤权限按钮列表
                $filteredMenu['auth_list'] = $this->filterAuthListByRoles($menu, $roleCodes);

                $filteredTree[] = $filteredMenu;
            }
        }

        return $filteredTree;
    }

    /**
     * 验证管理员是否有访问指定菜单的权限
     * @param int $menuId 菜单ID
     * @param int $adminId 管理员ID
     * @return bool 是否有权限
     * @throws ApiException
     */
    public function hasMenuAccess(int $menuId, int $adminId): bool
    {
        try {
            // 获取菜单信息
            $menu = $this->menuModel->find($menuId);
            if (!$menu || !$menu->isActive()) {
                return false;
            }

            // 获取管理员角色
            $admin = $this->adminModel->find($adminId);
            if (!$admin) {
                return false;
            }

            $roles = $admin->getRoles();
            if ($roles->isEmpty()) {
                return false;
            }

            // 获取激活的角色代码
            $roleCodes = [];
            foreach ($roles as $role) {
                if ($role->isActive()) {
                    $roleCodes[] = $role->code;
                }
            }

            if (empty($roleCodes)) {
                return false;
            }

            // 检查菜单权限
            return $this->hasMenuPermission($menu->toArray(), $roleCodes);
        } catch (\Exception $e) {
            throw new ApiException('菜单权限验证失败: ' . $e->getMessage(), Code::MENU_PERMISSION_ERROR);
        }
    }

    /**
     * 验证管理员是否有执行指定权限按钮的权限
     * @param int $menuId 菜单ID
     * @param string $authMark 权限标识
     * @param int $adminId 管理员ID
     * @return bool 是否有权限
     * @throws ApiException
     */
    public function hasButtonPermission(int $menuId, string $authMark, int $adminId): bool
    {
        try {
            // 首先检查是否有菜单访问权限
            if (!$this->hasMenuAccess($menuId, $adminId)) {
                return false;
            }

            // 获取菜单信息
            $menu = $this->menuModel->find($menuId);
            if (!$menu) {
                return false;
            }

            // 获取管理员角色
            $admin = $this->adminModel->find($adminId);
            if (!$admin) {
                return false;
            }

            $roles = $admin->getRoles();
            if ($roles->isEmpty()) {
                return false;
            }

            // 获取激活的角色代码
            $roleCodes = [];
            foreach ($roles as $role) {
                if ($role->isActive()) {
                    $roleCodes[] = $role->code;
                }
            }

            if (empty($roleCodes)) {
                return false;
            }

            // 检查权限按钮权限
            return $this->hasAuthButtonPermission($menu->toArray(), $authMark, $roleCodes);
        } catch (\Exception $e) {
            throw new ApiException('按钮权限验证失败: ' . $e->getMessage(), Code::MENU_PERMISSION_ERROR);
        }
    }

    /**
     * 获取管理员可访问的菜单ID列表
     * @param int $adminId 管理员ID
     * @return array 菜单ID列表
     * @throws ApiException
     */
    public function getAccessibleMenuIds(int $adminId): array
    {
        try {
            // 获取管理员信息
            $admin = $this->adminModel->find($adminId);
            if (!$admin) {
                throw new ApiException('管理员不存在', Code::ADMIN_NOT_FOUND);
            }

            // 获取管理员的角色
            $roles = $admin->getRoles();
            if ($roles->isEmpty()) {
                return [];
            }

            // 收集所有角色的菜单ID
            $menuIds = [];
            foreach ($roles as $role) {
                if ($role->isActive()) {
                    $roleMenuIds = $role->getMenuIds();
                    $menuIds = array_merge($menuIds, $roleMenuIds);
                }
            }

            // 去重并过滤激活的菜单
            $uniqueMenuIds = array_unique($menuIds);
            $accessibleIds = [];

            foreach ($uniqueMenuIds as $menuId) {
                $menu = $this->menuModel->find($menuId);
                if ($menu && $menu->isActive()) {
                    $accessibleIds[] = $menuId;
                }
            }

            return $accessibleIds;
        } catch (\Exception $e) {
            throw new ApiException('获取可访问菜单失败: ' . $e->getMessage(), Code::MENU_PERMISSION_ERROR);
        }
    }

    /**
     * 获取管理员的菜单权限列表（包含权限按钮）
     * @param int $adminId 管理员ID
     * @return array 权限列表
     * @throws ApiException
     */
    public function getMenuPermissions(int $adminId): array
    {
        try {
            // 获取可访问的菜单ID
            $menuIds = $this->getAccessibleMenuIds($adminId);
            if (empty($menuIds)) {
                return [];
            }

            // 获取菜单详细信息
            $menus = $this->menuModel->whereIn('id', $menuIds)->get();
            
            // 获取管理员角色代码
            $admin = $this->adminModel->find($adminId);
            $roles = $admin->getRoles();
            $roleCodes = [];
            foreach ($roles as $role) {
                if ($role->isActive()) {
                    $roleCodes[] = $role->code;
                }
            }

            // 构建权限列表
            $permissions = [];
            foreach ($menus as $menu) {
                $menuArray = $menu->toArray();
                
                // 添加菜单基础权限
                if (!empty($menuArray['permission'])) {
                    $permissions[] = $menuArray['permission'];
                }

                // 添加权限按钮权限
                $authList = $this->filterAuthListByRoles($menuArray, $roleCodes);
                foreach ($authList as $auth) {
                    if (!empty($auth['authMark'])) {
                        $permissions[] = $auth['authMark'];
                    }
                }
            }

            return array_unique($permissions);
        } catch (\Exception $e) {
            throw new ApiException('获取菜单权限失败: ' . $e->getMessage(), Code::MENU_PERMISSION_ERROR);
        }
    }

    /**
     * 批量验证管理员的菜单权限
     * @param array $menuIds 菜单ID列表
     * @param int $adminId 管理员ID
     * @return array 权限验证结果 ['menuId' => bool, ...]
     * @throws ApiException
     */
    public function batchCheckMenuAccess(array $menuIds, int $adminId): array
    {
        $results = [];

        foreach ($menuIds as $menuId) {
            $results[$menuId] = $this->hasMenuAccess($menuId, $adminId);
        }

        return $results;
    }

    /**
     * 检查菜单是否激活
     * @param array $menu 菜单数据
     * @return bool 是否激活
     */
    private function isMenuActive(array $menu): bool
    {
        return ($menu['status'] ?? true) && !($menu['deleted'] ?? false);
    }

    /**
     * 检查是否有菜单权限
     * @param array $menu 菜单数据
     * @param array $roleCodes 角色代码列表
     * @return bool 是否有权限
     */
    private function hasMenuPermission(array $menu, array $roleCodes): bool
    {
        // 解析菜单的角色权限
        $menuRoles = $this->parseMenuRoles($menu);

        // 如果菜单没有设置角色权限，则所有人都可以访问
        if (empty($menuRoles)) {
            return true;
        }

        // 检查用户角色是否与菜单角色有交集
        return !empty(array_intersect($roleCodes, $menuRoles));
    }

    /**
     * 检查是否有权限按钮权限
     * @param array $menu 菜单数据
     * @param string $authMark 权限标识
     * @param array $roleCodes 角色代码列表
     * @return bool 是否有权限
     */
    private function hasAuthButtonPermission(array $menu, string $authMark, array $roleCodes): bool
    {
        // 首先检查基础菜单权限
        if (!$this->hasMenuPermission($menu, $roleCodes)) {
            return false;
        }

        // 获取权限按钮列表
        $authList = $this->parseAuthList($menu);
        if (empty($authList)) {
            return false;
        }

        // 查找指定的权限按钮
        foreach ($authList as $auth) {
            if (($auth['authMark'] ?? '') === $authMark) {
                // 检查权限按钮的角色权限（如果有的话）
                if (isset($auth['roles']) && !empty($auth['roles'])) {
                    return !empty(array_intersect($roleCodes, $auth['roles']));
                }
                // 如果权限按钮没有单独的角色权限，则继承菜单权限
                return true;
            }
        }

        return false;
    }

    /**
     * 根据角色过滤权限按钮列表
     * @param array $menu 菜单数据
     * @param array $roleCodes 角色代码列表
     * @return array 过滤后的权限按钮列表
     */
    private function filterAuthListByRoles(array $menu, array $roleCodes): array
    {
        $authList = $this->parseAuthList($menu);
        if (empty($authList)) {
            return [];
        }

        $filteredAuthList = [];
        foreach ($authList as $auth) {
            // 检查权限按钮的角色权限
            if (isset($auth['roles']) && !empty($auth['roles'])) {
                // 如果权限按钮有单独的角色权限，检查交集
                if (!empty(array_intersect($roleCodes, $auth['roles']))) {
                    $filteredAuthList[] = $auth;
                }
            } else {
                // 如果权限按钮没有单独的角色权限，则继承菜单权限
                if ($this->hasMenuPermission($menu, $roleCodes)) {
                    $filteredAuthList[] = $auth;
                }
            }
        }

        return $filteredAuthList;
    }

    /**
     * 解析菜单的角色权限
     * @param array $menu 菜单数据
     * @return array 角色代码列表
     */
    private function parseMenuRoles(array $menu): array
    {
        $roles = $menu['roles'] ?? null;

        if (is_null($roles)) {
            return [];
        }

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($roles) ? $roles : [];
    }

    /**
     * 解析权限按钮列表
     * @param array $menu 菜单数据
     * @return array 权限按钮列表
     */
    private function parseAuthList(array $menu): array
    {
        $authList = $menu['auth_list'] ?? null;

        if (is_null($authList)) {
            return [];
        }

        if (is_string($authList)) {
            $decoded = json_decode($authList, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($authList) ? $authList : [];
    }

    /**
     * 验证角色是否有权限访问菜单
     * @param int $roleId 角色ID
     * @param int $menuId 菜单ID
     * @return bool 是否有权限
     * @throws ApiException
     */
    public function hasRoleMenuAccess(int $roleId, int $menuId): bool
    {
        try {
            // 获取角色信息
            $role = $this->roleModel->find($roleId);
            if (!$role || !$role->isActive()) {
                return false;
            }

            // 检查角色是否有此菜单权限
            return $role->hasMenu($menuId);
        } catch (\Exception $e) {
            throw new ApiException('角色菜单权限验证失败: ' . $e->getMessage(), Code::MENU_PERMISSION_ERROR);
        }
    }

    /**
     * 获取角色可访问的菜单树
     * @param int $roleId 角色ID
     * @return array 菜单树
     * @throws ApiException
     */
    public function getRoleMenuTree(int $roleId): array
    {
        try {
            // 获取角色信息
            $role = $this->roleModel->find($roleId);
            if (!$role || !$role->isActive()) {
                return [];
            }

            // 获取角色的菜单ID列表
            $menuIds = $role->getMenuIds();
            if (empty($menuIds)) {
                return [];
            }

            // 构建菜单树
            return $this->buildMenuTreeFromIds($menuIds);
        } catch (\Exception $e) {
            throw new ApiException('获取角色菜单树失败: ' . $e->getMessage(), Code::MENU_PERMISSION_ERROR);
        }
    }

    /**
     * 根据菜单ID列表构建菜单树
     * @param array $menuIds 菜单ID列表
     * @return array 菜单树
     */
    private function buildMenuTreeFromIds(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }

        // 获取所有相关菜单（包括父菜单）
        $allMenuIds = $this->getAllParentMenuIds($menuIds);
        
        // 获取菜单数据
        $menus = $this->menuModel->whereIn('id', $allMenuIds)
                                 ->where('status', 1)
                                 ->where('deleted', 0)
                                 ->orderBy('sort', 'asc')
                                 ->orderBy('id', 'asc')
                                 ->get();

        // 构建菜单映射
        $menuMap = [];
        foreach ($menus as $menu) {
            $menuArray = $menu->toArray();
            $menuMap[$menuArray['id']] = $menuArray;
            $menuMap[$menuArray['id']]['children'] = [];
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
     * 获取所有父菜单ID（确保树形结构完整）
     * @param array $menuIds 菜单ID列表
     * @return array 包含所有父菜单的ID列表
     */
    private function getAllParentMenuIds(array $menuIds): array
    {
        $allIds = $menuIds;
        
        foreach ($menuIds as $menuId) {
            $parentIds = $this->getParentMenuIds($menuId);
            $allIds = array_merge($allIds, $parentIds);
        }

        return array_unique($allIds);
    }

    /**
     * 获取指定菜单的所有父菜单ID
     * @param int $menuId 菜单ID
     * @return array 父菜单ID列表
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
     * 检查管理员是否为超级管理员
     * @param int $adminId 管理员ID
     * @return bool 是否为超级管理员
     */
    public function isSuperAdmin(int $adminId): bool
    {
        try {
            $admin = $this->adminModel->find($adminId);
            if (!$admin) {
                return false;
            }

            // 检查是否有超级管理员角色
            return $admin->hasRole('super_admin') || $admin->hasRole('SUPER_ADMIN');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取菜单权限验证统计信息
     * @param int $adminId 管理员ID
     * @return array 统计信息
     * @throws ApiException
     */
    public function getPermissionStats(int $adminId): array
    {
        try {
            $stats = [
                'total_menus' => $this->menuModel->where('status', 1)->where('deleted', 0)->count(),
                'accessible_menus' => 0,
                'total_permissions' => 0,
                'accessible_permissions' => 0,
                'roles_count' => 0,
                'is_super_admin' => $this->isSuperAdmin($adminId)
            ];

            // 获取可访问的菜单
            $accessibleMenuIds = $this->getAccessibleMenuIds($adminId);
            $stats['accessible_menus'] = count($accessibleMenuIds);

            // 获取权限统计
            $permissions = $this->getMenuPermissions($adminId);
            $stats['accessible_permissions'] = count($permissions);

            // 获取角色数量
            $admin = $this->adminModel->find($adminId);
            if ($admin) {
                $roles = $admin->getRoles();
                $stats['roles_count'] = $roles->count();
            }

            // 计算总权限数量
            $allMenus = $this->menuModel->where('status', 1)->where('deleted', 0)->get();
            $totalPermissions = [];
            foreach ($allMenus as $menu) {
                $menuArray = $menu->toArray();
                if (!empty($menuArray['permission'])) {
                    $totalPermissions[] = $menuArray['permission'];
                }
                
                $authList = $this->parseAuthList($menuArray);
                foreach ($authList as $auth) {
                    if (!empty($auth['authMark'])) {
                        $totalPermissions[] = $auth['authMark'];
                    }
                }
            }
            $stats['total_permissions'] = count(array_unique($totalPermissions));

            return $stats;
        } catch (\Exception $e) {
            throw new ApiException('获取权限统计失败: ' . $e->getMessage(), Code::MENU_PERMISSION_ERROR);
        }
    }
}