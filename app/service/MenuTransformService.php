<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\model\Menu;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\ErrorCode;

/**
 * 菜单数据转换服务
 * 负责处理数据库数据与前端配置之间的转换
 */
class MenuTransformService
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
     * 数据库数据转换为前端路由配置
     * @param array $menuData 数据库菜单数据
     * @return array 前端路由配置
     * @throws ApiException
     */
    public function toRouteConfig(array $menuData): array
    {
        try {
            // 跳过按钮类型的菜单项
            if (($menuData['type'] ?? '') === Menu::TYPE_BUTTON) {
                return [];
            }

            $config = [
                'id' => $menuData['id'] ?? 0,
                'name' => $menuData['name'] ?? '',
                'path' => $menuData['path'] ?? '',
                'component' => $this->getComponentPath($menuData),
                'meta' => $this->buildMetaConfig($menuData)
            ];

            // 添加重定向路径
            if (!empty($menuData['redirect'])) {
                $config['redirect'] = $menuData['redirect'];
            }

            return $config;
        } catch (\Exception $e) {
            throw new ApiException('菜单路由配置转换失败: ' . $e->getMessage(), ErrorCode::MENU_TRANSFORM_ERROR);
        }
    }

    /**
     * 批量转换菜单树为前端路由配置
     * @param array $menuTree 菜单树数据
     * @return array 前端路由配置数组
     * @throws ApiException
     */
    public function toRouteConfigTree(array $menuTree): array
    {
        $routes = [];

        foreach ($menuTree as $menu) {
            $routeConfig = $this->toRouteConfig($menu);
            
            // 如果是按钮类型，跳过
            if (empty($routeConfig)) {
                continue;
            }

            // 处理子菜单
            if (!empty($menu['children'])) {
                $children = $this->toRouteConfigTree($menu['children']);
                if (!empty($children)) {
                    $routeConfig['children'] = $children;
                }
            }

            $routes[] = $routeConfig;
        }

        return $routes;
    }

    /**
     * 前端表单数据转换为数据库格式
     * @param array $formData 前端表单数据
     * @return array 数据库格式数据
     * @throws ApiException
     */
    public function fromFormData(array $formData): array
    {
        try {
            $dbData = [
                'parent_id' => $this->sanitizeInteger($formData['parent_id'] ?? 0),
                'name' => $this->sanitizeString($formData['name'] ?? ''),
                'path' => $this->sanitizeString($formData['path'] ?? ''),
                'component' => $this->sanitizeString($formData['component'] ?? ''),
                'redirect' => $this->sanitizeString($formData['redirect'] ?? ''),
                'title' => $this->sanitizeString($formData['title'] ?? ''),
                'icon' => $this->sanitizeString($formData['icon'] ?? ''),
                'type' => $this->sanitizeString($formData['type'] ?? Menu::TYPE_DIRECTORY),
                'permission' => $this->sanitizeString($formData['permission'] ?? ''),
                'hidden' => $this->sanitizeBoolean($formData['hidden'] ?? false),
                'cacheable' => $this->sanitizeBoolean($formData['cacheable'] ?? true),
                'affix' => $this->sanitizeBoolean($formData['affix'] ?? false),
                'full_page' => $this->sanitizeBoolean($formData['full_page'] ?? false),
                'link_url' => $this->sanitizeString($formData['link_url'] ?? ''),
                'iframe' => $this->sanitizeBoolean($formData['iframe'] ?? false),
                'show_badge' => $this->sanitizeBoolean($formData['show_badge'] ?? false),
                'badge_text' => $this->sanitizeString($formData['badge_text'] ?? ''),
                'active_path' => $this->sanitizeString($formData['active_path'] ?? ''),
                'status' => $this->sanitizeBoolean($formData['status'] ?? true),
                'sort' => $this->sanitizeInteger($formData['sort'] ?? 100)
            ];

            // 处理JSON字段
            $dbData['roles'] = $this->processRolesField($formData['roles'] ?? []);
            $dbData['auth_list'] = $this->processAuthListField($formData['auth_list'] ?? []);

            return $dbData;
        } catch (\Exception $e) {
            throw new ApiException('表单数据转换失败: ' . $e->getMessage(), ErrorCode::MENU_TRANSFORM_ERROR);
        }
    }

    /**
     * 数据库数据转换为前端表单数据
     * @param array $dbData 数据库数据
     * @return array 前端表单数据
     * @throws ApiException
     */
    public function toFormData(array $dbData): array
    {
        try {
            $formData = [
                'id' => $dbData['id'] ?? 0,
                'parent_id' => $dbData['parent_id'] ?? 0,
                'name' => $dbData['name'] ?? '',
                'path' => $dbData['path'] ?? '',
                'component' => $dbData['component'] ?? '',
                'redirect' => $dbData['redirect'] ?? '',
                'title' => $dbData['title'] ?? '',
                'icon' => $dbData['icon'] ?? '',
                'type' => $dbData['type'] ?? Menu::TYPE_DIRECTORY,
                'permission' => $dbData['permission'] ?? '',
                'hidden' => (bool)($dbData['hidden'] ?? false),
                'cacheable' => (bool)($dbData['cacheable'] ?? true),
                'affix' => (bool)($dbData['affix'] ?? false),
                'full_page' => (bool)($dbData['full_page'] ?? false),
                'link_url' => $dbData['link_url'] ?? '',
                'iframe' => (bool)($dbData['iframe'] ?? false),
                'show_badge' => (bool)($dbData['show_badge'] ?? false),
                'badge_text' => $dbData['badge_text'] ?? '',
                'active_path' => $dbData['active_path'] ?? '',
                'status' => (bool)($dbData['status'] ?? true),
                'sort' => $dbData['sort'] ?? 100
            ];

            // 处理JSON字段
            $formData['roles'] = $this->parseJsonField($dbData['roles'] ?? null, []);
            $formData['auth_list'] = $this->parseJsonField($dbData['auth_list'] ?? null, []);

            return $formData;
        } catch (\Exception $e) {
            throw new ApiException('数据库数据转换失败: ' . $e->getMessage(), ErrorCode::MENU_TRANSFORM_ERROR);
        }
    }

    /**
     * 构建前端路由meta配置
     * @param array $menuData 菜单数据
     * @return array meta配置
     */
    private function buildMetaConfig(array $menuData): array
    {
        $meta = [
            'title' => $menuData['title'] ?? '',
            'icon' => $menuData['icon'] ?? '',
            'keepAlive' => (bool)($menuData['cacheable'] ?? true),
            'isHide' => (bool)($menuData['hidden'] ?? false),
            'fixedTab' => (bool)($menuData['affix'] ?? false),
            'isFullPage' => (bool)($menuData['full_page'] ?? false),
            'showBadge' => (bool)($menuData['show_badge'] ?? false)
        ];

        // 添加权限标识
        if (!empty($menuData['permission'])) {
            $meta['permission'] = $menuData['permission'];
        }

        // 处理角色权限
        $roles = $this->parseJsonField($menuData['roles'] ?? null, []);
        if (!empty($roles)) {
            $meta['roles'] = $roles;
        }

        // 处理外链配置
        if (!empty($menuData['link_url'])) {
            $meta['link'] = $menuData['link_url'];
            $meta['isIframe'] = (bool)($menuData['iframe'] ?? false);
        }

        // 处理徽章文本
        if (!empty($menuData['badge_text'])) {
            $meta['showTextBadge'] = $menuData['badge_text'];
        }

        // 处理权限按钮列表
        $authList = $this->parseJsonField($menuData['auth_list'] ?? null, []);
        if (!empty($authList)) {
            $meta['authList'] = $authList;
        }

        // 处理激活路径
        if (!empty($menuData['active_path'])) {
            $meta['activePath'] = $menuData['active_path'];
        }

        return $meta;
    }

    /**
     * 获取组件路径
     * @param array $menuData 菜单数据
     * @return string 组件路径
     */
    private function getComponentPath(array $menuData): string
    {
        $component = $menuData['component'] ?? '';
        
        // 如果没有指定组件路径，根据菜单类型设置默认值
        if (empty($component)) {
            $type = $menuData['type'] ?? Menu::TYPE_DIRECTORY;
            
            switch ($type) {
                case Menu::TYPE_DIRECTORY:
                    return 'Layout';
                case Menu::TYPE_LINK:
                case Menu::TYPE_IFRAME:
                    return 'IframeView';
                default:
                    return 'Layout';
            }
        }

        return $component;
    }

    /**
     * 处理角色权限字段
     * @param mixed $roles 角色数据
     * @return array|null 处理后的角色数组
     * @throws ApiException
     */
    private function processRolesField($roles): ?array
    {
        if (empty($roles)) {
            return null;
        }

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ApiException('角色权限JSON格式错误: ' . json_last_error_msg(), ErrorCode::INVALID_JSON_FORMAT);
            }
            $roles = $decoded;
        }

        if (!is_array($roles)) {
            throw new ApiException('角色权限必须是数组格式', ErrorCode::INVALID_MENU_DATA);
        }

        // 验证角色数组格式
        if (!Menu::validateRoles($roles)) {
            throw new ApiException('角色权限数组格式不正确', ErrorCode::INVALID_MENU_DATA);
        }

        return array_values(array_unique(array_filter($roles))); // 去重并过滤空值
    }

    /**
     * 处理权限按钮列表字段
     * @param mixed $authList 权限按钮列表数据
     * @return array|null 处理后的权限按钮列表
     * @throws ApiException
     */
    private function processAuthListField($authList): ?array
    {
        if (empty($authList)) {
            return null;
        }

        if (is_string($authList)) {
            $decoded = json_decode($authList, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ApiException('权限按钮列表JSON格式错误: ' . json_last_error_msg(), ErrorCode::INVALID_JSON_FORMAT);
            }
            $authList = $decoded;
        }

        if (!is_array($authList)) {
            throw new ApiException('权限按钮列表必须是数组格式', ErrorCode::INVALID_MENU_DATA);
        }

        // 验证权限按钮列表格式
        if (!Menu::validateAuthList($authList)) {
            throw new ApiException('权限按钮列表格式不正确', ErrorCode::INVALID_MENU_DATA);
        }

        // 标准化权限按钮数据
        $standardizedList = [];
        foreach ($authList as $auth) {
            $standardizedList[] = [
                'title' => trim($auth['title'] ?? ''),
                'authMark' => trim($auth['authMark'] ?? '')
            ];
        }

        return $standardizedList;
    }

    /**
     * 解析JSON字段
     * @param mixed $value JSON字段值
     * @param mixed $default 默认值
     * @return mixed 解析后的值
     */
    private function parseJsonField($value, $default = null)
    {
        if (is_null($value)) {
            return $default;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        }

        return is_array($value) ? $value : $default;
    }

    /**
     * 清理字符串数据
     * @param mixed $value 输入值
     * @return string 清理后的字符串
     */
    private function sanitizeString($value): string
    {
        if (is_null($value)) {
            return '';
        }
        
        return trim((string)$value);
    }

    /**
     * 清理整数数据
     * @param mixed $value 输入值
     * @return int 清理后的整数
     */
    private function sanitizeInteger($value): int
    {
        return (int)$value;
    }

    /**
     * 清理布尔数据
     * @param mixed $value 输入值
     * @return bool 清理后的布尔值
     */
    private function sanitizeBoolean($value): bool
    {
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        }
        
        return (bool)$value;
    }

    /**
     * 验证菜单数据完整性
     * @param array $data 菜单数据
     * @return array 验证结果 ['valid' => bool, 'errors' => array]
     */
    public function validateMenuData(array $data): array
    {
        return $this->menuModel->validateMenuData($data);
    }

    /**
     * 格式化菜单数据用于API响应
     * @param array $menuData 菜单数据
     * @return array 格式化后的数据
     */
    public function formatForApi(array $menuData): array
    {
        $formatted = [
            'id' => $menuData['id'] ?? 0,
            'parent_id' => $menuData['parent_id'] ?? 0,
            'name' => $menuData['name'] ?? '',
            'path' => $menuData['path'] ?? '',
            'component' => $menuData['component'] ?? '',
            'redirect' => $menuData['redirect'] ?? '',
            'title' => $menuData['title'] ?? '',
            'icon' => $menuData['icon'] ?? '',
            'type' => $menuData['type'] ?? Menu::TYPE_DIRECTORY,
            'type_text' => Menu::TYPE_MAP[$menuData['type'] ?? Menu::TYPE_DIRECTORY] ?? '未知',
            'permission' => $menuData['permission'] ?? '',
            'hidden' => (bool)($menuData['hidden'] ?? false),
            'cacheable' => (bool)($menuData['cacheable'] ?? true),
            'affix' => (bool)($menuData['affix'] ?? false),
            'full_page' => (bool)($menuData['full_page'] ?? false),
            'link_url' => $menuData['link_url'] ?? '',
            'iframe' => (bool)($menuData['iframe'] ?? false),
            'show_badge' => (bool)($menuData['show_badge'] ?? false),
            'badge_text' => $menuData['badge_text'] ?? '',
            'active_path' => $menuData['active_path'] ?? '',
            'status' => (bool)($menuData['status'] ?? true),
            'sort' => $menuData['sort'] ?? 100,
            'created_at' => $menuData['created_at'] ?? null,
            'updated_at' => $menuData['updated_at'] ?? null
        ];

        // 处理JSON字段
        $formatted['roles'] = $this->parseJsonField($menuData['roles'] ?? null, []);
        $formatted['auth_list'] = $this->parseJsonField($menuData['auth_list'] ?? null, []);

        return $formatted;
    }

    /**
     * 批量格式化菜单数据用于API响应
     * @param array $menuList 菜单数据列表
     * @return array 格式化后的数据列表
     */
    public function batchFormatForApi(array $menuList): array
    {
        $formatted = [];
        
        foreach ($menuList as $menu) {
            $formatted[] = $this->formatForApi($menu);
        }

        return $formatted;
    }

    /**
     * 构建菜单选择器数据
     * @param array $menuTree 菜单树数据
     * @param bool $includeButtons 是否包含按钮类型
     * @return array 选择器数据
     */
    public function buildSelectorData(array $menuTree, bool $includeButtons = false): array
    {
        $selectorData = [];

        foreach ($menuTree as $menu) {
            // 如果不包含按钮类型，则跳过
            if (!$includeButtons && ($menu['type'] ?? '') === Menu::TYPE_BUTTON) {
                continue;
            }

            $item = [
                'value' => $menu['id'] ?? 0,
                'label' => $menu['title'] ?? '',
                'type' => $menu['type'] ?? Menu::TYPE_DIRECTORY,
                'disabled' => !($menu['status'] ?? true)
            ];

            // 处理子菜单
            if (!empty($menu['children'])) {
                $children = $this->buildSelectorData($menu['children'], $includeButtons);
                if (!empty($children)) {
                    $item['children'] = $children;
                }
            }

            $selectorData[] = $item;
        }

        return $selectorData;
    }

    /**
     * 构建面包屑数据
     * @param array $menuPath 菜单路径数据
     * @return array 面包屑数据
     */
    public function buildBreadcrumbData(array $menuPath): array
    {
        $breadcrumbs = [];

        foreach ($menuPath as $menu) {
            $breadcrumbs[] = [
                'title' => $menu['title'] ?? '',
                'path' => $menu['path'] ?? '',
                'name' => $menu['name'] ?? ''
            ];
        }

        return $breadcrumbs;
    }

    /**
     * 过滤菜单数据（根据权限）
     * @param array $menuTree 菜单树数据
     * @param array $userRoles 用户角色
     * @return array 过滤后的菜单树
     */
    public function filterByRoles(array $menuTree, array $userRoles): array
    {
        $filtered = [];

        foreach ($menuTree as $menu) {
            // 检查菜单权限
            $menuRoles = $this->parseJsonField($menu['roles'] ?? null, []);
            
            // 如果菜单没有设置角色权限，或者用户角色与菜单角色有交集，则显示
            if (empty($menuRoles) || !empty(array_intersect($userRoles, $menuRoles))) {
                $filteredMenu = $menu;
                
                // 递归过滤子菜单
                if (!empty($menu['children'])) {
                    $filteredChildren = $this->filterByRoles($menu['children'], $userRoles);
                    $filteredMenu['children'] = $filteredChildren;
                }
                
                $filtered[] = $filteredMenu;
            }
        }

        return $filtered;
    }

    /**
     * 提取菜单中的权限标识列表
     * @param array $menuTree 菜单树数据
     * @return array 权限标识列表
     */
    public function extractPermissions(array $menuTree): array
    {
        $permissions = [];

        foreach ($menuTree as $menu) {
            // 添加菜单权限
            if (!empty($menu['permission'])) {
                $permissions[] = $menu['permission'];
            }

            // 添加权限按钮权限
            $authList = $this->parseJsonField($menu['auth_list'] ?? null, []);
            foreach ($authList as $auth) {
                if (!empty($auth['authMark'])) {
                    $permissions[] = $auth['authMark'];
                }
            }

            // 递归处理子菜单
            if (!empty($menu['children'])) {
                $childPermissions = $this->extractPermissions($menu['children']);
                $permissions = array_merge($permissions, $childPermissions);
            }
        }

        return array_unique(array_filter($permissions));
    }

    /**
     * 构建菜单统计信息
     * @param array $menuTree 菜单树数据
     * @return array 统计信息
     */
    public function buildStatistics(array $menuTree): array
    {
        $stats = [
            'total_count' => 0,
            'directory_count' => 0,
            'menu_count' => 0,
            'button_count' => 0,
            'link_count' => 0,
            'iframe_count' => 0,
            'hidden_count' => 0,
            'disabled_count' => 0,
            'max_depth' => 0
        ];

        $this->calculateStatistics($menuTree, $stats, 1);

        return $stats;
    }

    /**
     * 递归计算菜单统计信息
     * @param array $menuTree 菜单树数据
     * @param array &$stats 统计信息引用
     * @param int $depth 当前深度
     */
    private function calculateStatistics(array $menuTree, array &$stats, int $depth): void
    {
        foreach ($menuTree as $menu) {
            $stats['total_count']++;
            $stats['max_depth'] = max($stats['max_depth'], $depth);

            // 统计菜单类型
            $type = $menu['type'] ?? Menu::TYPE_DIRECTORY;
            switch ($type) {
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

            // 统计状态
            if ($menu['hidden'] ?? false) {
                $stats['hidden_count']++;
            }
            
            if (!($menu['status'] ?? true)) {
                $stats['disabled_count']++;
            }

            // 递归处理子菜单
            if (!empty($menu['children'])) {
                $this->calculateStatistics($menu['children'], $stats, $depth + 1);
            }
        }
    }
}