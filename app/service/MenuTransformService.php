<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\Menu;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;

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
     * 缓存配置
     * @var array
     */
    private array $cacheConfig;

    /**
     * 缓存实例
     * @var mixed
     */
    private $cache;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->menuModel = ModelFactory::menu();
        $this->loadCacheConfig();
        $this->initializeCache();
    }

    /**
     * 加载缓存配置
     */
    private function loadCacheConfig(): void
    {
        $configFile = base_path() . '/plugin/theadmin/config/cache.php';
        
        if (file_exists($configFile)) {
            $this->cacheConfig = require $configFile;
        } else {
            $this->cacheConfig = [
                'menu' => ['enabled' => false],
            ];
        }
    }

    /**
     * 初始化缓存
     */
    private function initializeCache(): void
    {
        if (!($this->cacheConfig['menu']['enabled'] ?? true)) {
            $this->cache = null;
            return;
        }

        try {
            $cacheType = $this->cacheConfig['type'] ?? 'file';
            
            if ($cacheType === 'redis' && class_exists('\Redis')) {
                $redisConfig = $this->cacheConfig['redis'] ?? [];
                $this->cache = new \Redis();
                
                $host = $redisConfig['host'] ?? '127.0.0.1';
                $port = $redisConfig['port'] ?? 6379;
                $timeout = $redisConfig['timeout'] ?? 2;
                
                if ($this->cache->connect($host, $port, $timeout)) {
                    // 设置密码（如果有）
                    if (!empty($redisConfig['password'])) {
                        $this->cache->auth($redisConfig['password']);
                    }
                    
                    // 选择数据库
                    $database = $redisConfig['database'] ?? 1;
                    $this->cache->select($database);
                } else {
                    throw new \Exception('Redis连接失败');
                }
            } else {
                // 使用文件缓存
                $this->cache = null;
            }
        } catch (\Exception $e) {
            // 缓存初始化失败，使用文件缓存
            $this->cache = null;
        }
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
                'parentId' => $menuData['parent_id'] ?? 0,
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
            throw new ApiException( Code::MENU_TRANSFORM_ERROR, '菜单路由配置转换失败: ' . $e->getMessage());
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
                'icon' => $this->sanitizeString($formData['icon'] ?? ''),
                'type' => $this->sanitizeString($formData['type'] ?? Menu::TYPE_DIRECTORY),
                'permission' => $this->sanitizeString($formData['permission'] ?? ''),
                'hide' => $this->sanitizeBoolean($formData['hide'] ?? false),
                'cacheable' => $this->sanitizeBoolean($formData['cacheable'] ?? true),
                'fixed_tab' => $this->sanitizeBoolean($formData['fixed_tab'] ?? false),
                'full_page' => $this->sanitizeBoolean($formData['full_page'] ?? false),
                'link' => $this->sanitizeString($formData['link'] ?? ''),
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
            throw new ApiException(Code::MENU_TRANSFORM_ERROR, '表单数据转换失败: ' . $e->getMessage());
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
                'icon' => $dbData['icon'] ?? '',
                'type' => $dbData['type'] ?? Menu::TYPE_DIRECTORY,
                'permission' => $dbData['permission'] ?? '',
                'hide' => (bool)($dbData['hide'] ?? false),
                'cacheable' => (bool)($dbData['cacheable'] ?? true),
                'fixed_tab' => (bool)($dbData['fixed_tab'] ?? false),
                'full_page' => (bool)($dbData['full_page'] ?? false),
                'link' => $dbData['link'] ?? '',
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
            throw new ApiException(Code::MENU_TRANSFORM_ERROR, '数据库数据转换失败: ' . $e->getMessage());
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
            'title' => $menuData['name'] ?? '',
            'icon' => $menuData['icon'] ?? '',
            'keepAlive' => $menuData['keepAlive'] ??  true,
            'isHide' =>  $menuData['isHide'] ?? false,
            'isHideTab' =>  $menuData['isHideTab'] ?? false,
            'fixedTab' => $menuData['fixedTab'] ??  false,
            'isFullPage' => $menuData['isFullPage'] ??  false,
            'showBadge' => $menuData['showBadge'] ??  false,
            'showTextBadge' => $menuData['showTextBadge'] ??  false
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

        // 处理外链配置（优先使用驼峰命名）
        $link = $menuData['link'] ?? ($menuData['link'] ?? '');
        if (!empty($link)) {
            $meta['link'] = $link;
            $meta['isIframe'] = (bool)($menuData['iframe'] ?? false);
        }

        // 处理徽章文本（优先使用驼峰命名）
        $badgeText = $menuData['badgeText'] ?? ($menuData['badge_text'] ?? '');
        if (!empty($badgeText)) {
            $meta['showTextBadge'] = $badgeText;
        }

        // 处理权限按钮列表
        $authList = $this->parseJsonField($menuData['auth_list'] ?? null, []);
        if (!empty($authList)) {
            $meta['authList'] = $authList;
        }

        // 处理激活路径（优先使用驼峰命名）
        $activePath = $menuData['activePath'] ?? ($menuData['active_path'] ?? '');
        if (!empty($activePath)) {
            $meta['activePath'] = $activePath;
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
                    return $menuData["parent_id"]?'':'/index/index';
                case Menu::TYPE_LINK:
                case Menu::TYPE_IFRAME:
                    return 'IframeView';
                default:
                    return '';
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
                throw new ApiException(Code::INVALID_JSON_FORMAT, '角色权限JSON格式错误: ' . json_last_error_msg());
            }
            $roles = $decoded;
        }

        if (!is_array($roles)) {
            throw new ApiException(Code::INVALID_MENU_DATA, '角色权限必须是数组格式');
        }

        // 验证角色数组格式
        if (!Menu::validateRoles($roles)) {
            throw new ApiException(Code::INVALID_MENU_DATA, '角色权限数组格式不正确');
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
                throw new ApiException(Code::INVALID_JSON_FORMAT, '权限按钮列表JSON格式错误: ' . json_last_error_msg());
            }
            $authList = $decoded;
        }

        if (!is_array($authList)) {
            throw new ApiException(Code::INVALID_MENU_DATA, '权限按钮列表必须是数组格式');
        }

        // 验证权限按钮列表格式
        if (!Menu::validateAuthList($authList)) {
            throw new ApiException(Code::INVALID_MENU_DATA, '权限按钮列表格式不正确');
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
        // 如果传入的是模型实例，直接转数组（会自动应用访问器）
        if ($menuData instanceof Menu) {
            $data = $menuData->toArray();
            $data['type_text'] = Menu::TYPE_MAP[$data['type'] ?? Menu::TYPE_DIRECTORY] ?? '未知';
            return $data;
        }

        // 手动格式化数组数据，使用驼峰命名
        return [
            'id' => $menuData['id'] ?? 0,
            'parent_id' => $menuData['parent_id'] ?? 0,
            'name' => $menuData['name'] ?? '',
            'path' => $menuData['path'] ?? '',
            'component' => $menuData['component'] ?? '',
            'redirect' => $menuData['redirect'] ?? '',
            'title' => $menuData['name'] ?? '',
            'icon' => $menuData['icon'] ?? '',
            'type' => $menuData['type'] ?? Menu::TYPE_DIRECTORY,
            'type_text' => Menu::TYPE_MAP[$menuData['type'] ?? Menu::TYPE_DIRECTORY] ?? '未知',
            'permission' => $menuData['permission'] ?? '',
            'hide' => (bool)($menuData['hide'] ?? false),
            // 使用驼峰命名的访问器字段
            'keepAlive' => $menuData['keepAlive'] ?? (bool)($menuData['cache'] ?? true),
            'fixedTab' => $menuData['fixedTab'] ?? (bool)($menuData['fixed_tab'] ?? false),
            'isFullPage' => $menuData['isFullPage'] ?? (bool)($menuData['full_page'] ?? false),
            'link' => $menuData['link'] ?? ($menuData['link'] ?? ''),
            'iframe' => (bool)($menuData['iframe'] ?? false),
            'showBadge' => $menuData['showBadge'] ?? (bool)($menuData['show_badge'] ?? false),
            'badgeText' => $menuData['badgeText'] ?? ($menuData['badge_text'] ?? ''),
            'activePath' => $menuData['activePath'] ?? ($menuData['active_path'] ?? ''),
            'status' => (bool)($menuData['status'] ?? true),
            'sort' => $menuData['sort'] ?? 100,
            'createdAt' => $menuData['createdAt'] ?? ($menuData['created_at'] ?? null),
            'updatedAt' => $menuData['updatedAt'] ?? ($menuData['updated_at'] ?? null),
        ];
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
                'label' => $menu['name'] ?? '',
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
            'hide_count' => 0,
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
            if ($menu['hide'] ?? false) {
                $stats['hide_count']++;
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

    // ==================== 缓存相关方法 ====================

    /**
     * 获取缓存键前缀
     * @return string
     */
    private function getCachePrefix(): string
    {
        $redisPrefix = $this->cacheConfig['redis']['prefix'] ?? 'theadmin:';
        $menuPrefix = $this->cacheConfig['menu']['prefix'] ?? 'menu:';
        return $redisPrefix . $menuPrefix;
    }

    /**
     * 获取缓存TTL
     * @param string $strategy 缓存策略名称
     * @return int
     */
    private function getCacheTTL(string $strategy = 'default'): int
    {
        $strategies = $this->cacheConfig['menu']['strategies'] ?? [];
        
        if (isset($strategies[$strategy]['ttl'])) {
            return $strategies[$strategy]['ttl'];
        }
        
        return $this->cacheConfig['menu']['ttl'] ?? 3600;
    }

    /**
     * 检查缓存策略是否启用
     * @param string $strategy 缓存策略名称
     * @return bool
     */
    private function isCacheStrategyEnabled(string $strategy): bool
    {
        if (!($this->cacheConfig['menu']['enabled'] ?? true)) {
            return false;
        }
        
        $strategies = $this->cacheConfig['menu']['strategies'] ?? [];
        return $strategies[$strategy]['enabled'] ?? true;
    }

    /**
     * 获取缓存数据
     * @param string $key 缓存键
     * @return mixed|null 缓存数据，不存在返回null
     */
    public function getCache(string $key)
    {
        try {
            $cacheKey = $this->getCachePrefix() . $key;
            
            if ($this->cache instanceof \Redis) {
                $data = $this->cache->get($cacheKey);
                if ($data === false) {
                    return null;
                }
                return json_decode($data, true);
            } else {
                // 使用文件缓存
                return $this->getFileCache($cacheKey);
            }
        } catch (\Exception $e) {
            // 缓存获取失败，返回null
            return null;
        }
    }

    /**
     * 设置缓存数据
     * @param string $key 缓存键
     * @param mixed $data 缓存数据
     * @param int|null $ttl 过期时间（秒），null使用默认值
     * @return bool 是否设置成功
     */
    public function setCache(string $key, $data, ?int $ttl = null): bool
    {
        try {
            $cacheKey = $this->getCachePrefix() . $key;
            $ttl = $ttl ?? $this->getCacheTTL();
            
            if ($this->cache instanceof \Redis) {
                $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
                return $this->cache->setex($cacheKey, $ttl, $jsonData);
            } else {
                // 使用文件缓存
                return $this->setFileCache($cacheKey, $data, $ttl);
            }
        } catch (\Exception $e) {
            // 缓存设置失败
            return false;
        }
    }

    /**
     * 删除缓存数据
     * @param string $key 缓存键
     * @return bool 是否删除成功
     */
    public function deleteCache(string $key): bool
    {
        try {
            $cacheKey = $this->getCachePrefix() . $key;
            
            if ($this->cache instanceof \Redis) {
                return $this->cache->del($cacheKey) > 0;
            } else {
                // 删除文件缓存
                return $this->deleteFileCache($cacheKey);
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 清空所有菜单缓存
     * @return bool 是否清空成功
     */
    public function clearAllCache(): bool
    {
        try {
            if ($this->cache instanceof \Redis) {
                $pattern = $this->getCachePrefix() . '*';
                $keys = $this->cache->keys($pattern);
                if (!empty($keys)) {
                    return $this->cache->del($keys) > 0;
                }
                return true;
            } else {
                // 清空文件缓存
                return $this->clearAllFileCache();
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取文件缓存
     * @param string $key 缓存键
     * @return mixed|null
     */
    private function getFileCache(string $key)
    {
        $cacheFile = $this->getCacheFilePath($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheData = file_get_contents($cacheFile);
        if ($cacheData === false) {
            return null;
        }

        $cache = json_decode($cacheData, true);
        if (!$cache || !isset($cache['expire_time'], $cache['data'])) {
            return null;
        }

        // 检查是否过期
        if (time() > $cache['expire_time']) {
            unlink($cacheFile);
            return null;
        }

        return $cache['data'];
    }

    /**
     * 设置文件缓存
     * @param string $key 缓存键
     * @param mixed $data 缓存数据
     * @param int $ttl 过期时间
     * @return bool
     */
    private function setFileCache(string $key, $data, int $ttl): bool
    {
        $cacheFile = $this->getCacheFilePath($key);
        $cacheDir = dirname($cacheFile);

        // 确保缓存目录存在
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheData = [
            'expire_time' => time() + $ttl,
            'data' => $data
        ];

        return file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE)) !== false;
    }

    /**
     * 删除文件缓存
     * @param string $key 缓存键
     * @return bool
     */
    private function deleteFileCache(string $key): bool
    {
        $cacheFile = $this->getCacheFilePath($key);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }

    /**
     * 清空所有文件缓存
     * @return bool
     */
    private function clearAllFileCache(): bool
    {
        $cachePath = $this->cacheConfig['file']['path'] ?? runtime_path() . '/cache/theadmin/';
        $prefix = $this->cacheConfig['file']['prefix'] ?? 'theadmin_';
        
        if (!is_dir($cachePath)) {
            return true;
        }

        $files = glob($cachePath . $prefix . '*.json');
        foreach ($files as $file) {
            if (!unlink($file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取缓存文件路径
     * @param string $key 缓存键
     * @return string
     */
    private function getCacheFilePath(string $key): string
    {
        $cachePath = $this->cacheConfig['file']['path'] ?? runtime_path() . '/cache/theadmin/';
        $prefix = $this->cacheConfig['file']['prefix'] ?? 'theadmin_';
        $safeKey = md5($key);
        return $cachePath . $prefix . $safeKey . '.json';
    }

    /**
     * 带缓存的菜单树形结构转换
     * @param array $menuTree 菜单树数据
     * @param array $userRoles 用户角色（用于缓存键）
     * @return array 前端路由配置数组
     * @throws ApiException
     */
    public function toRouteConfigTreeWithCache(array $menuTree, array $userRoles = []): array
    {
        // 检查缓存策略是否启用
        if (!$this->isCacheStrategyEnabled('route_config')) {
            return $this->toRouteConfigTree($menuTree);
        }

        // 生成缓存键
        $cacheKey = 'route_tree:' . md5(json_encode($menuTree) . implode(',', $userRoles));
        
        // 尝试从缓存获取
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 缓存未命中，执行转换
        $routes = $this->toRouteConfigTree($menuTree);
        
        // 存储到缓存
        $ttl = $this->getCacheTTL('route_config');
        $this->setCache($cacheKey, $routes, $ttl);
        
        return $routes;
    }

    /**
     * 带缓存的菜单权限过滤
     * @param array $menuTree 菜单树数据
     * @param array $userRoles 用户角色
     * @return array 过滤后的菜单树
     */
    public function filterByRolesWithCache(array $menuTree, array $userRoles): array
    {
        // 检查缓存策略是否启用
        if (!$this->isCacheStrategyEnabled('role_filter')) {
            return $this->filterByRoles($menuTree, $userRoles);
        }

        // 生成缓存键
        $cacheKey = 'filtered_menu:' . md5(json_encode($menuTree) . implode(',', $userRoles));
        
        // 尝试从缓存获取
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 缓存未命中，执行过滤
        $filtered = $this->filterByRoles($menuTree, $userRoles);
        
        // 存储到缓存
        $ttl = $this->getCacheTTL('role_filter');
        $this->setCache($cacheKey, $filtered, $ttl);
        
        return $filtered;
    }

    /**
     * 带缓存的菜单选择器数据构建
     * @param array $menuTree 菜单树数据
     * @param bool $includeButtons 是否包含按钮类型
     * @return array 选择器数据
     */
    public function buildSelectorDataWithCache(array $menuTree, bool $includeButtons = false): array
    {
        // 检查缓存策略是否启用
        if (!$this->isCacheStrategyEnabled('selector_data')) {
            return $this->buildSelectorData($menuTree, $includeButtons);
        }

        // 生成缓存键
        $cacheKey = 'selector_data:' . md5(json_encode($menuTree) . ($includeButtons ? '1' : '0'));
        
        // 尝试从缓存获取
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 缓存未命中，执行构建
        $selectorData = $this->buildSelectorData($menuTree, $includeButtons);
        
        // 存储到缓存
        $ttl = $this->getCacheTTL('selector_data');
        $this->setCache($cacheKey, $selectorData, $ttl);
        
        return $selectorData;
    }

    /**
     * 带缓存的权限提取
     * @param array $menuTree 菜单树数据
     * @return array 权限标识列表
     */
    public function extractPermissionsWithCache(array $menuTree): array
    {
        // 检查缓存策略是否启用
        if (!$this->isCacheStrategyEnabled('permissions')) {
            return $this->extractPermissions($menuTree);
        }

        // 生成缓存键
        $cacheKey = 'permissions:' . md5(json_encode($menuTree));
        
        // 尝试从缓存获取
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 缓存未命中，执行提取
        $permissions = $this->extractPermissions($menuTree);
        
        // 存储到缓存
        $ttl = $this->getCacheTTL('permissions');
        $this->setCache($cacheKey, $permissions, $ttl);
        
        return $permissions;
    }

    /**
     * 带缓存的统计信息构建
     * @param array $menuTree 菜单树数据
     * @return array 统计信息
     */
    public function buildStatisticsWithCache(array $menuTree): array
    {
        // 检查缓存策略是否启用
        if (!$this->isCacheStrategyEnabled('statistics')) {
            return $this->buildStatistics($menuTree);
        }

        // 生成缓存键
        $cacheKey = 'statistics:' . md5(json_encode($menuTree));
        
        // 尝试从缓存获取
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 缓存未命中，执行构建
        $stats = $this->buildStatistics($menuTree);
        
        // 存储到缓存
        $ttl = $this->getCacheTTL('statistics');
        $this->setCache($cacheKey, $stats, $ttl);
        
        return $stats;
    }

    /**
     * 菜单数据变更时清理相关缓存
     * @param int|null $menuId 菜单ID，null表示清理所有缓存
     * @return bool 是否清理成功
     */
    public function invalidateCache(?int $menuId = null): bool
    {
        if ($menuId === null) {
            // 清理所有菜单缓存
            return $this->clearAllCache();
        }

        // 清理特定菜单相关的缓存
        // 由于缓存键包含了菜单数据的哈希，菜单变更后哈希会改变，旧缓存自然失效
        // 这里可以选择性地清理一些通用缓存
        $keysToDelete = [
            'route_tree:*',
            'filtered_menu:*',
            'selector_data:*',
            'permissions:*',
            'statistics:*'
        ];

        $success = true;
        foreach ($keysToDelete as $pattern) {
            if (!$this->deleteCacheByPattern($pattern)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 根据模式删除缓存
     * @param string $pattern 缓存键模式
     * @return bool 是否删除成功
     */
    private function deleteCacheByPattern(string $pattern): bool
    {
        try {
            if ($this->cache instanceof \Redis) {
                $fullPattern = self::CACHE_PREFIX . $pattern;
                $keys = $this->cache->keys($fullPattern);
                if (!empty($keys)) {
                    return $this->cache->del($keys) > 0;
                }
                return true;
            } else {
                // 文件缓存模式下，清理所有缓存
                return $this->clearAllFileCache();
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取缓存统计信息
     * @return array 缓存统计信息
     */
    public function getCacheStats(): array
    {
        $stats = [
            'cache_type' => $this->cache instanceof \Redis ? 'redis' : 'file',
            'total_keys' => 0,
            'cache_size' => 0,
            'hit_rate' => 0.0
        ];

        try {
            if ($this->cache instanceof \Redis) {
                $pattern = self::CACHE_PREFIX . '*';
                $keys = $this->cache->keys($pattern);
                $stats['total_keys'] = count($keys);
                
                // 计算缓存大小（近似值）
                foreach ($keys as $key) {
                    $stats['cache_size'] += strlen($this->cache->get($key) ?: '');
                }
            } else {
                // 文件缓存统计
                $cachePath = $this->cacheConfig['file']['path'] ?? runtime_path() . '/cache/theadmin/';
                $prefix = $this->cacheConfig['file']['prefix'] ?? 'theadmin_';
                
                if (is_dir($cachePath)) {
                    $files = glob($cachePath . $prefix . '*.json');
                    $stats['total_keys'] = count($files);
                    
                    foreach ($files as $file) {
                        $stats['cache_size'] += filesize($file);
                    }
                }
            }
        } catch (\Exception $e) {
            // 统计失败，返回默认值
        }

        return $stats;
    }

    /**
     * 预热缓存
     * @param array $menuTree 菜单树数据
     * @param array $commonRoles 常用角色列表，为空时使用配置中的角色
     * @return bool 是否预热成功
     */
    public function warmupCache(array $menuTree, array $commonRoles = []): bool
    {
        try {
            // 如果没有提供角色列表，使用配置中的角色
            if (empty($commonRoles)) {
                $commonRoles = $this->cacheConfig['menu']['warmup_roles'] ?? [
                    ['admin'],
                    ['user'],
                    ['admin', 'user']
                ];
            }

            // 预热路由配置缓存
            if ($this->isCacheStrategyEnabled('route_config')) {
                $this->toRouteConfigTreeWithCache($menuTree);
                
                // 为常用角色预热路由配置
                foreach ($commonRoles as $roles) {
                    $this->toRouteConfigTreeWithCache($menuTree, $roles);
                }
            }
            
            // 预热选择器数据缓存
            if ($this->isCacheStrategyEnabled('selector_data')) {
                $this->buildSelectorDataWithCache($menuTree, false);
                $this->buildSelectorDataWithCache($menuTree, true);
            }
            
            // 预热权限列表缓存
            if ($this->isCacheStrategyEnabled('permissions')) {
                $this->extractPermissionsWithCache($menuTree);
            }
            
            // 预热统计信息缓存
            if ($this->isCacheStrategyEnabled('statistics')) {
                $this->buildStatisticsWithCache($menuTree);
            }
            
            // 为常用角色预热过滤缓存
            if ($this->isCacheStrategyEnabled('role_filter')) {
                foreach ($commonRoles as $roles) {
                    $this->filterByRolesWithCache($menuTree, $roles);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}