<?php

namespace plugin\theadmin\app\validator;

use plugin\theadmin\app\model\Menu;

/**
 * 菜单数据验证器
 * 实现菜单基础字段验证、JSON字段格式验证、层级关系验证和路由路径唯一性验证
 */
class MenuValidator
{
    /**
     * 验证错误信息
     * @var array
     */
    private array $errors = [];

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
     * 验证菜单数据
     * @param array $data 菜单数据
     * @param int $menuId 菜单ID（更新时传入，新增时为0）
     * @return bool
     */
    public function validate(array $data, int $menuId = 0): bool
    {
        $this->errors = [];

        // 验证基础字段
        $this->validateBasicFields($data);

        // 验证JSON字段格式
        $this->validateJsonFields($data);

        // 验证菜单层级关系
        $this->validateHierarchy($data, $menuId);

        // 验证路由路径唯一性
        $this->validatePathUniqueness($data, $menuId);

        // 验证业务逻辑
        $this->validateBusinessLogic($data);

        return empty($this->errors);
    }

    /**
     * 获取验证错误信息
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取第一个错误信息
     * @return string
     */
    public function getFirstError(): string
    {
        return $this->errors[0] ?? '';
    }

    /**
     * 验证基础字段
     * @param array $data 菜单数据
     * @return void
     */
    private function validateBasicFields(array $data): void
    {
        // 验证菜单名称（必填）
        if (empty($data['name'])) {
            $this->errors[] = '菜单名称不能为空';
        } elseif (!$this->validateName($data['name'])) {
            $this->errors[] = '菜单名称格式不正确，只能包含字母、数字、下划线、中划线、中文字符，长度2-100';
        }

        // 验证菜单标题（必填）
        if (empty($data['title'])) {
            $this->errors[] = '菜单标题不能为空';
        } elseif (!$this->validateTitle($data['title'])) {
            $this->errors[] = '菜单标题长度必须在2-100个字符之间';
        }

        // 验证菜单类型（必填）
        if (!isset($data['type']) || empty($data['type'])) {
            $this->errors[] = '菜单类型不能为空';
        } elseif (!$this->validateMenuType($data['type'])) {
            $this->errors[] = '菜单类型不正确，必须是：D(目录)、M(菜单)、B(按钮)、L(外链)、I(内嵌)';
        }

        // 验证路由路径格式
        if (isset($data['path']) && !empty($data['path']) && !$this->validatePath($data['path'])) {
            $this->errors[] = '路由路径格式不正确，必须以/开头，只能包含字母、数字、下划线、中划线、斜杠，长度不超过200';
        }

        // 验证组件路径格式
        if (isset($data['component']) && !empty($data['component']) && !$this->validateComponent($data['component'])) {
            $this->errors[] = '组件路径格式不正确，只能包含字母、数字、下划线、中划线、斜杠、点号，长度不超过200';
        }

        // 验证重定向路径格式
        if (isset($data['redirect']) && !empty($data['redirect']) && !$this->validatePath($data['redirect'])) {
            $this->errors[] = '重定向路径格式不正确，必须以/开头，只能包含字母、数字、下划线、中划线、斜杠，长度不超过200';
        }

        // 验证权限标识格式
        if (isset($data['permission']) && !empty($data['permission']) && !$this->validatePermission($data['permission'])) {
            $this->errors[] = '权限标识格式不正确，只能包含字母、数字、下划线、中划线、冒号，长度不超过100';
        }

        // 验证图标格式
        if (isset($data['icon']) && !empty($data['icon']) && !$this->validateIcon($data['icon'])) {
            $this->errors[] = '图标格式不正确，长度不超过100个字符';
        }

        // 验证外链URL格式
        if (isset($data['link_url']) && !empty($data['link_url']) && !$this->validateUrl($data['link_url'])) {
            $this->errors[] = '外链地址格式不正确或长度超过500个字符';
        }

        // 验证徽章文本长度
        if (isset($data['badge_text']) && !empty($data['badge_text']) && !$this->validateBadgeText($data['badge_text'])) {
            $this->errors[] = '徽章文本长度不能超过20个字符';
        }

        // 验证激活路径格式
        if (isset($data['active_path']) && !empty($data['active_path']) && !$this->validatePath($data['active_path'])) {
            $this->errors[] = '激活路径格式不正确，必须以/开头，只能包含字母、数字、下划线、中划线、斜杠，长度不超过200';
        }

        // 验证排序值
        if (isset($data['sort']) && !$this->validateSort($data['sort'])) {
            $this->errors[] = '排序值必须是0-9999之间的整数';
        }

        // 验证父菜单ID
        if (isset($data['parent_id']) && !$this->validateParentId($data['parent_id'])) {
            $this->errors[] = '父菜单ID必须是非负整数';
        }
    }

    /**
     * 验证JSON字段格式
     * @param array $data 菜单数据
     * @return void
     */
    private function validateJsonFields(array $data): void
    {
        // 验证角色权限数组
        if (isset($data['roles'])) {
            if (is_string($data['roles'])) {
                // 如果是字符串，尝试解析JSON
                $roles = json_decode($data['roles'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->errors[] = '角色权限数据JSON格式错误：' . json_last_error_msg();
                } elseif (!$this->validateRoles($roles)) {
                    $this->errors[] = '角色权限数组格式不正确，必须是字符串数组';
                }
            } elseif (is_array($data['roles'])) {
                if (!$this->validateRoles($data['roles'])) {
                    $this->errors[] = '角色权限数组格式不正确，必须是字符串数组';
                }
            } elseif (!is_null($data['roles'])) {
                $this->errors[] = '角色权限数据类型错误，必须是数组或JSON字符串';
            }
        }

        // 验证权限按钮列表
        if (isset($data['auth_list'])) {
            if (is_string($data['auth_list'])) {
                // 如果是字符串，尝试解析JSON
                $authList = json_decode($data['auth_list'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->errors[] = '权限按钮列表JSON格式错误：' . json_last_error_msg();
                } elseif (!$this->validateAuthList($authList)) {
                    $this->errors[] = '权限按钮列表格式不正确';
                }
            } elseif (is_array($data['auth_list'])) {
                if (!$this->validateAuthList($data['auth_list'])) {
                    $this->errors[] = '权限按钮列表格式不正确';
                }
            } elseif (!is_null($data['auth_list'])) {
                $this->errors[] = '权限按钮列表数据类型错误，必须是数组或JSON字符串';
            }
        }
    }

    /**
     * 验证菜单层级关系
     * @param array $data 菜单数据
     * @param int $menuId 菜单ID
     * @return void
     */
    private function validateHierarchy(array $data, int $menuId): void
    {
        $parentId = $data['parent_id'] ?? 0;

        // 如果没有父菜单，跳过层级验证
        if ($parentId <= 0) {
            return;
        }

        // 验证父菜单是否存在
        $parent = $this->menuModel->find($parentId);
        if (!$parent) {
            $this->errors[] = '父菜单不存在';
            return;
        }

        // 验证父菜单是否已删除
        if ($parent->deleted) {
            $this->errors[] = '父菜单已被删除，无法选择';
            return;
        }

        // 更新操作时，不能将自己设为父菜单
        if ($menuId > 0 && $parentId == $menuId) {
            $this->errors[] = '不能将自己设为父菜单';
            return;
        }

        // 检查是否会形成循环引用
        if ($menuId > 0 && $this->wouldCreateCircularReference($menuId, $parentId)) {
            $this->errors[] = '不能选择子菜单作为父菜单，会形成循环引用';
            return;
        }

        // 验证菜单层级深度（最多5级）
        $depth = $this->getMenuDepth($parentId) + 1;
        if ($depth > 5) {
            $this->errors[] = '菜单层级不能超过5级';
            return;
        }

        // 验证菜单类型层级关系
        $this->validateTypeHierarchy($data, $parent);
    }

    /**
     * 验证路由路径唯一性
     * @param array $data 菜单数据
     * @param int $menuId 菜单ID
     * @return void
     */
    private function validatePathUniqueness(array $data, int $menuId): void
    {
        $path = $data['path'] ?? '';
        
        // 如果路径为空，跳过唯一性验证
        if (empty($path)) {
            return;
        }

        // 查询是否存在相同路径的菜单
        $query = $this->menuModel->where('path', $path)->where('deleted', false);
        
        // 更新操作时排除当前菜单
        if ($menuId > 0) {
            $query->where('id', '<>', $menuId);
        }

        $existingMenu = $query->find();
        if ($existingMenu) {
            $this->errors[] = "路由路径 '{$path}' 已被菜单 '{$existingMenu->title}' 使用";
        }
    }

    /**
     * 验证业务逻辑
     * @param array $data 菜单数据
     * @return void
     */
    private function validateBusinessLogic(array $data): void
    {
        $type = $data['type'] ?? '';

        // 根据菜单类型验证必要字段
        switch ($type) {
            case Menu::TYPE_MENU:
                // 菜单页面必须有路径和组件
                if (empty($data['path'])) {
                    $this->errors[] = '菜单页面必须设置路由路径';
                }
                if (empty($data['component'])) {
                    $this->errors[] = '菜单页面必须设置组件路径';
                }
                break;

            case Menu::TYPE_BUTTON:
                // 权限按钮必须有权限标识
                if (empty($data['permission'])) {
                    $this->errors[] = '权限按钮必须设置权限标识';
                }
                break;

            case Menu::TYPE_LINK:
            case Menu::TYPE_IFRAME:
                // 外链和内嵌页面必须有链接地址
                if (empty($data['link_url'])) {
                    $this->errors[] = '外链菜单必须设置链接地址';
                }
                break;
        }

        // 验证外链配置的一致性
        if (!empty($data['link_url'])) {
            // 有外链地址时，菜单类型应该是外链或内嵌
            if (!in_array($type, [Menu::TYPE_LINK, Menu::TYPE_IFRAME])) {
                $this->errors[] = '设置了外链地址的菜单类型应该是外链或内嵌';
            }
        }

        // 验证内嵌配置
        if (isset($data['iframe']) && $data['iframe']) {
            // 启用内嵌时必须有外链地址
            if (empty($data['link_url'])) {
                $this->errors[] = '启用内嵌显示时必须设置外链地址';
            }
        }

        // 验证徽章配置
        if (isset($data['show_badge']) && $data['show_badge'] && empty($data['badge_text'])) {
            // 显示徽章时建议设置徽章文本（警告，不阻止保存）
            // $this->errors[] = '启用徽章显示时建议设置徽章文本';
        }
    }

    /**
     * 验证菜单名称格式
     * @param string $name 菜单名称
     * @return bool
     */
    private function validateName(string $name): bool
    {
        // 菜单名称只能包含字母、数字、下划线、中划线、中文字符，长度2-100
        return preg_match('/^[a-zA-Z0-9_\-\x{4e00}-\x{9fa5}]{2,100}$/u', $name);
    }

    /**
     * 验证菜单标题格式
     * @param string $title 菜单标题
     * @return bool
     */
    private function validateTitle(string $title): bool
    {
        // 菜单标题长度2-100，不能为空
        $title = trim($title);
        return !empty($title) && mb_strlen($title) >= 2 && mb_strlen($title) <= 100;
    }

    /**
     * 验证菜单类型
     * @param string $type 菜单类型
     * @return bool
     */
    private function validateMenuType(string $type): bool
    {
        return in_array($type, [
            Menu::TYPE_DIRECTORY,
            Menu::TYPE_MENU,
            Menu::TYPE_BUTTON,
            Menu::TYPE_LINK,
            Menu::TYPE_IFRAME
        ]);
    }

    /**
     * 验证路由路径格式
     * @param string $path 路由路径
     * @return bool
     */
    private function validatePath(string $path): bool
    {
        // 路径必须以/开头，只能包含字母、数字、下划线、中划线、斜杠，长度不超过200
        return preg_match('/^\/[a-zA-Z0-9_\-\/]*$/', $path) && strlen($path) <= 200;
    }

    /**
     * 验证组件路径格式
     * @param string $component 组件路径
     * @return bool
     */
    private function validateComponent(string $component): bool
    {
        // 组件路径只能包含字母、数字、下划线、中划线、斜杠、点号，长度不超过200
        return preg_match('/^[a-zA-Z0-9_\-\/\.]*$/', $component) && strlen($component) <= 200;
    }

    /**
     * 验证权限标识格式
     * @param string $permission 权限标识
     * @return bool
     */
    private function validatePermission(string $permission): bool
    {
        // 权限标识只能包含字母、数字、下划线、中划线、冒号，长度不超过100
        return preg_match('/^[a-zA-Z0-9_\-:]*$/', $permission) && strlen($permission) <= 100;
    }

    /**
     * 验证图标格式
     * @param string $icon 图标
     * @return bool
     */
    private function validateIcon(string $icon): bool
    {
        // 图标长度不超过100个字符
        return strlen($icon) <= 100;
    }

    /**
     * 验证外链URL格式
     * @param string $url 外链URL
     * @return bool
     */
    private function validateUrl(string $url): bool
    {
        // 验证URL格式并检查长度
        return filter_var($url, FILTER_VALIDATE_URL) !== false && strlen($url) <= 500;
    }

    /**
     * 验证徽章文本长度
     * @param string $badgeText 徽章文本
     * @return bool
     */
    private function validateBadgeText(string $badgeText): bool
    {
        return mb_strlen($badgeText) <= 20;
    }

    /**
     * 验证排序值
     * @param mixed $sort 排序值
     * @return bool
     */
    private function validateSort($sort): bool
    {
        return is_numeric($sort) && $sort >= 0 && $sort <= 9999;
    }

    /**
     * 验证父菜单ID
     * @param mixed $parentId 父菜单ID
     * @return bool
     */
    private function validateParentId($parentId): bool
    {
        return is_numeric($parentId) && $parentId >= 0;
    }

    /**
     * 验证角色权限数组格式
     * @param array $roles 角色权限数组
     * @return bool
     */
    private function validateRoles(array $roles): bool
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
    private function validateAuthList(array $authList): bool
    {
        if (empty($authList)) {
            return true; // 权限列表可以为空
        }

        // 检查每个权限按钮的格式
        foreach ($authList as $auth) {
            if (!is_array($auth)) {
                return false;
            }

            // 必须包含title和authMark字段
            if (!isset($auth['title']) || !isset($auth['authMark'])) {
                return false;
            }

            // title和authMark必须是非空字符串
            if (!is_string($auth['title']) || !is_string($auth['authMark'])) {
                return false;
            }

            if (empty($auth['title']) || empty($auth['authMark'])) {
                return false;
            }

            // 验证authMark格式（权限标识格式）
            if (!$this->validatePermission($auth['authMark'])) {
                return false;
            }
        }

        return true;
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
            $parent = $this->menuModel->find($currentParentId);
            $currentParentId = $parent ? $parent->parent_id : 0;
        }

        return false;
    }

    /**
     * 获取菜单深度
     * @param int $menuId 菜单ID
     * @return int
     */
    private function getMenuDepth(int $menuId): int
    {
        $depth = 0;
        $currentId = $menuId;

        while ($currentId > 0) {
            $menu = $this->menuModel->find($currentId);
            if (!$menu) {
                break;
            }

            $depth++;
            $currentId = $menu->parent_id;

            // 防止无限循环
            if ($depth > 10) {
                break;
            }
        }

        return $depth;
    }

    /**
     * 验证菜单类型层级关系
     * @param array $data 菜单数据
     * @param Menu $parent 父菜单
     * @return void
     */
    private function validateTypeHierarchy(array $data, Menu $parent): void
    {
        $type = $data['type'] ?? '';
        $parentType = $parent->type;

        // 按钮类型只能在菜单页面下
        if ($type === Menu::TYPE_BUTTON && $parentType !== Menu::TYPE_MENU) {
            $this->errors[] = '权限按钮只能添加在菜单页面下';
        }

        // 菜单页面不能在按钮下
        if ($type === Menu::TYPE_MENU && $parentType === Menu::TYPE_BUTTON) {
            $this->errors[] = '菜单页面不能添加在权限按钮下';
        }

        // 外链和内嵌页面可以在目录或菜单下，但不建议嵌套太深
        if (in_array($type, [Menu::TYPE_LINK, Menu::TYPE_IFRAME])) {
            $depth = $this->getMenuDepth($parent->id) + 1;
            if ($depth > 3) {
                $this->errors[] = '外链菜单不建议嵌套超过3级';
            }
        }
    }

    /**
     * 验证菜单数据的完整性（快速验证方法）
     * @param array $data 菜单数据
     * @return array 验证结果 ['valid' => bool, 'errors' => array]
     */
    public function quickValidate(array $data): array
    {
        $this->errors = [];

        // 只验证最基本的必填字段
        if (empty($data['name'])) {
            $this->errors[] = '菜单名称不能为空';
        }

        if (empty($data['title'])) {
            $this->errors[] = '菜单标题不能为空';
        }

        if (!isset($data['type']) || empty($data['type'])) {
            $this->errors[] = '菜单类型不能为空';
        }

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors
        ];
    }

    /**
     * 验证批量操作数据
     * @param array $batchData 批量数据
     * @return array 验证结果
     */
    public function validateBatch(array $batchData): array
    {
        $results = [];
        
        foreach ($batchData as $index => $data) {
            $menuId = $data['id'] ?? 0;
            $isValid = $this->validate($data, $menuId);
            
            $results[$index] = [
                'valid' => $isValid,
                'errors' => $this->getErrors(),
                'data' => $data
            ];
            
            // 重置错误信息
            $this->errors = [];
        }
        
        return $results;
    }

    /**
     * 验证菜单排序数据
     * @param array $sortData 排序数据
     * @return bool
     */
    public function validateSortData(array $sortData): bool
    {
        $this->errors = [];

        if (empty($sortData)) {
            $this->errors[] = '排序数据不能为空';
            return false;
        }

        foreach ($sortData as $index => $item) {
            if (!is_array($item)) {
                $this->errors[] = "排序数据第{$index}项格式错误";
                continue;
            }

            if (!isset($item['id']) || !is_numeric($item['id']) || $item['id'] <= 0) {
                $this->errors[] = "排序数据第{$index}项缺少有效的菜单ID";
            }

            if (isset($item['sort']) && (!is_numeric($item['sort']) || $item['sort'] < 0)) {
                $this->errors[] = "排序数据第{$index}项排序值无效";
            }

            if (isset($item['parent_id']) && (!is_numeric($item['parent_id']) || $item['parent_id'] < 0)) {
                $this->errors[] = "排序数据第{$index}项父菜单ID无效";
            }
        }

        return empty($this->errors);
    }
}