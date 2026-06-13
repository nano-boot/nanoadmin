<?php

namespace plugin\nanoadmin\app\validator;

/**
 * 菜单验证器
 *
 * @author TheAdmin Team
 * @since 1.0.0
 */
class MenuValidator extends ValidatorBase
{
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'parent_id' => 'integer|min:0',
        'name' => 'require|string|min:1|max:100',
        'path' => 'string|max:200',
        'component' => 'string|max:200',
        'redirect' => 'string|max:200',
        'icon' => 'string|max:100',
        'type' => 'require|in:D,M,B,L,I',
        'permission' => 'string|max:100',
        'hidden' => 'boolean',
        'hide_tab' => 'boolean',
        'full_page' => 'boolean',
        'keep_alive' => 'boolean',
        'fixed_tab' => 'boolean',
        'link' => 'string|max:500',
        'iframe' => 'boolean',
        'show_badge' => 'boolean',
        'badge_text' => 'string|max:20',
        'active_path' => 'string|max:200',
        'status' => 'boolean',
        'sort' => 'integer|min:0|max:9999',
        'page' => 'integer|min:1',
        'limit' => 'integer|min:1|max:100',
        'keyword' => 'string|max:100',
        'ids' => 'require|array|min:1',
        'ids.*' => 'integer|gt:0',
        '__button_permission_unique__' => 'checkButtonPermissionUnique'
    ];

    protected $message = [
        'parent_id.integer' => '父菜单ID必须是整数',
        'parent_id.min' => '父菜单ID必须大于等于0',
        'name.required' => '菜单名称不能为空',
        'name.string' => '菜单名称必须是字符串',
        'name.min' => '菜单名称长度必须在1-100个字符之间',
        'name.max' => '菜单名称长度必须在1-100个字符之间',
        'path.string' => '路由路径必须是字符串',
        'path.max' => '路由路径长度不能超过200个字符',
        'component.string' => '组件路径必须是字符串',
        'component.max' => '组件路径长度不能超过200个字符',
        'redirect.string' => '重定向路径必须是字符串',
        'redirect.max' => '重定向路径长度不能超过200个字符',
        'icon.string' => '菜单图标必须是字符串',
        'icon.max' => '菜单图标长度不能超过100个字符',
        'type.required' => '菜单类型不能为空',
        'type.in' => '菜单类型只能是D(目录)、M(菜单)、B(按钮)、L(外链)、I(内嵌)中的一种',
        'permission.string' => '权限标识必须是字符串',
        'permission.max' => '权限标识长度不能超过100个字符',
        '__button_permission_unique__.checkButtonPermissionUnique' => '按钮权限标识不能重复',
        'hidden.boolean' => '是否隐藏必须是布尔值',
        'hide_tab.boolean' => '是否隐藏标签页必须是布尔值',
        'full_page.boolean' => '是否全屏显示必须是布尔值',
        'keep_alive.boolean' => '是否缓存必须是布尔值',
        'fixed_tab.boolean' => '是否固定标签必须是布尔值',
        'link.string' => '外链地址必须是字符串',
        'link.max' => '外链地址长度不能超过500个字符',
        'iframe.boolean' => '是否内嵌必须是布尔值',
        'show_badge.boolean' => '是否显示徽章必须是布尔值',
        'badge_text.string' => '徽章文本必须是字符串',
        'badge_text.max' => '徽章文本长度不能超过20个字符',
        'active_path.string' => '激活菜单路径必须是字符串',
        'active_path.max' => '激活菜单路径长度不能超过200个字符',
        'status.boolean' => '状态必须是布尔值',
        'sort.integer' => '排序必须是整数',
        'sort.min' => '排序必须大于等于0',
        'sort.max' => '排序必须小于等于9999',
        'id.required' => 'ID不能为空',
        'id.integer' => 'ID必须是整数',
        'id.gt' => 'ID必须大于0',
        'page.integer' => '页码必须是整数',
        'page.min' => '页码必须大于0',
        'limit.integer' => '每页数量必须是整数',
        'limit.min' => '每页数量必须大于0',
        'limit.max' => '每页数量不能超过100',
        'keyword.string' => '关键词必须是字符串',
        'keyword.max' => '关键词长度不能超过100个字符',
        'ids.required' => 'ID数组不能为空',
        'ids.array' => 'ID必须是数组',
        'ids.min' => '至少选择一个项目',
        'ids.*.integer' => 'ID必须是整数',
        'ids.*.gt' => 'ID必须大于0'
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'store' => [
            'parent_id', 'name', 'path', 'component', 'redirect',
            'icon', 'type', 'permission', 'hidden', 'hide_tab', 'full_page',
            'keep_alive', 'fixed_tab', 'link', 'iframe', 'show_badge',
            'badge_text', 'active_path', 'status', 'sort'
        ],
        'update' => [
            'id', 'parent_id', 'name', 'path', 'component', 'redirect',
            'icon', 'type', 'permission', 'hidden', 'hide_tab', 'full_page',
            'keep_alive', 'fixed_tab', 'link', 'iframe', 'show_badge',
            'badge_text', 'active_path', 'status', 'sort'
        ],
        'show' => ['id'],
        'destroy' => ['id'],
        'batchDestroy' => ['ids'],
        'index' => ['page', 'limit', 'keyword', 'status', 'type', 'parent_id'],
    ];

    /**
     * 自定义验证规则：根据菜单类型验证必填字段
     */
    protected function checkMenuTypeFields($value, $rule, $data = [])
    {
        $type = $data['type'] ?? '';
        
        switch ($type) {
            case 'D': // 目录
                // 目录不需要组件路径
                if (!empty($data['component'])) {
                    return '目录类型不应该设置组件路径';
                }
                break;
                
            case 'M': // 菜单
                // 菜单需要路径和组件
                if (empty($data['path'])) {
                    return '菜单类型必须设置路由路径';
                }
                if (empty($data['component'])) {
                    return '菜单类型必须设置组件路径';
                }
                break;
                
            case 'B': // 按钮
                // 按钮必须挂在父级菜单下
                if ((int)($data['parent_id'] ?? 0) <= 0) {
                    return '按钮节点必须选择父级菜单';
                }
                // 按钮需要权限标识
                if (empty($data['permission'])) {
                    return '按钮类型必须设置权限标识';
                }
                // 按钮不需要路径和组件
                if (!empty($data['path']) || !empty($data['component'])) {
                    return '按钮类型不应该设置路由路径和组件路径';
                }
                break;
                
            case 'L': // 外链
                // 外链需要外链地址
                if (empty($data['link'])) {
                    return '外链类型必须设置外链地址';
                }
                break;
                
            case 'I': // 内嵌
                // 内嵌需要外链地址
                if (empty($data['link'])) {
                    return '内嵌类型必须设置外链地址';
                }
                break;
        }
        
        return true;
    }

    /**
     * 自定义验证规则：按钮权限标识唯一
     */
    protected function checkButtonPermissionUnique($value, $rule, $data = [])
    {
        if (($data['type'] ?? '') !== 'B') {
            return true;
        }

        $permission = trim((string)($data['permission'] ?? ''));
        if ($permission === '') {
            return true;
        }

        $query = new \plugin\nanoadmin\app\model\Menu();
        $query = $query
            ->where('type', 'B')
            ->where('permission', $permission);

        $excludeId = isset($data['id']) ? (int)$data['id'] : 0;
        if ($excludeId > 0) {
            $query = $query->where('id', '<>', $excludeId);
        }

        $duplicate = $query->first();
        if (!$duplicate) {
            return true;
        }

        return '已存在相同的权限标识「' . $permission . '」（菜单名称：' . $duplicate->name . '）';
    }

    /**
     * 创建场景的自定义验证
     */
    protected function sceneStore()
    {
        //  引用 $scene['store'] 数组，避免重复定义
        return $this->only($this->scene['store'])
            ->append('__button_permission_unique__', 'checkButtonPermissionUnique')
            ->append('type', 'checkMenuTypeFields');
    }

    /**
     * 更新场景的自定义验证
     */
    protected function sceneUpdate()
    {
        return $this->only($this->scene['update'])
            ->append('__button_permission_unique__', 'checkButtonPermissionUnique')
            ->append('type', 'checkMenuTypeFields');
    }
}