<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\menu;

use plugin\nanoadmin\app\model\Menu;
use plugin\nanoadmin\app\validator\ValidatorBase;
use support\validation\Rule;

/**
 * 菜单验证器
 *
 * 使用示例：
 * ```php
 * // 控制器中
 * $data = $validator->scene('store')->setPost()->check();
 *
 * // 带上下文（排除自身）
 * $data = $validator->withContext(['excludeId' => $id])->scene('update')->setPost()->check();
 * ```
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
class MenuValidator extends ValidatorBase
{
    /**
     * 模型类（用于 unique/exists 规则自动解析表名）
     *
     * @var string|null
     */
    protected ?string $model = Menu::class;

    /**
     * 主键字段
     */
    protected string $primaryKey = 'id';

    /**
     * 验证规则
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'gt:0',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'name' => [
                'required',
                'string',
                'min:1',
                'max:100',
            ],
            'path' => [
                'nullable',
                'string',
                'max:200',
            ],
            'component' => [
                'nullable',
                'string',
                'max:200',
            ],
            'redirect' => [
                'nullable',
                'string',
                'max:200',
            ],
            'icon' => [
                'nullable',
                'string',
                'max:100',
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['D', 'M', 'B', 'L', 'I']),
            ],
            'permission' => [
                'nullable',
                'string',
                'max:100',
            ],
            'hidden' => [
                'nullable',
                'boolean',
            ],
            'hide_tab' => [
                'nullable',
                'boolean',
            ],
            'full_page' => [
                'nullable',
                'boolean',
            ],
            'keep_alive' => [
                'nullable',
                'boolean',
            ],
            'fixed_tab' => [
                'nullable',
                'boolean',
            ],
            'link' => [
                'nullable',
                'string',
                'max:500',
            ],
            'iframe' => [
                'nullable',
                'boolean',
            ],
            'show_badge' => [
                'nullable',
                'boolean',
            ],
            'badge_text' => [
                'nullable',
                'string',
                'max:20',
            ],
            'active_path' => [
                'nullable',
                'string',
                'max:200',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
            'sort' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'keyword' => [
                'nullable',
                'string',
                'max:100',
            ],
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'integer',
                'gt:0',
            ],
            'type_filter' => [
                'nullable',
                'string',
                Rule::in(['D', 'M', 'B', 'L', 'I']),
            ],
            'parent_id_filter' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'only_enabled' => [
                'nullable',
                'boolean',
            ],
            'sort_data' => [
                'required',
                'array',
                'min:1',
            ],
            'sort_data.*' => [
                'array',
            ],
            'sort_data.*.id' => [
                'required',
                'integer',
                'gt:0',
            ],
            'sort_data.*.sort' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
            'sort_data.*.parent_id' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * 自定义消息
     */
    public function messages(): array
    {
        return [
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
            'ids.*.gt' => 'ID必须大于0',

            'type_filter.in' => '菜单类型过滤值只能是D(目录)、M(菜单)、B(按钮)、L(外链)、I(内嵌)中的一种',
            'parent_id_filter.integer' => '父菜单ID过滤必须是整数',
            'parent_id_filter.min' => '父菜单ID过滤必须大于等于0',
        ];
    }

    /**
     * 场景定义
     */
    public function scenes(): array
    {
        return [
            'store' => [
                'parent_id',
                'name',
                'path',
                'component',
                'redirect',
                'icon',
                'type',
                'permission',
                'hidden',
                'hide_tab',
                'full_page',
                'keep_alive',
                'fixed_tab',
                'link',
                'iframe',
                'show_badge',
                'badge_text',
                'active_path',
                'status',
                'sort',
            ],

            'update' => [
                'id',
                'parent_id',
                'name',
                'path',
                'component',
                'redirect',
                'icon',
                'type',
                'permission',
                'hidden',
                'hide_tab',
                'full_page',
                'keep_alive',
                'fixed_tab',
                'link',
                'iframe',
                'show_badge',
                'badge_text',
                'active_path',
                'status',
                'sort',
            ],

            'tree' => [
                'parent_id',
                'keyword',
                'status',
                'hidden',
                'only_enabled',
            ],

            'route' => [],

            'show' => ['id'],
            'destroy' => ['id'],
            'batchDestroy' => ['ids'],

            'sort' => ['sort_data'],

            'index' => ['page', 'limit', 'keyword', 'status', 'type_filter', 'parent_id_filter'],
        ];
    }

    /**
     * 构建按钮权限校验规则（更新场景使用）
     *
     * @param array $allRules
     * @return array
     */
    protected function buildButtonPermissionRules(array $allRules): array
    {
        $data = $this->data();
        $type = $data['type'] ?? '';

        // 只有按钮类型才需要校验权限唯一性
        if ($type === 'B') {
            return [
                'permission' => [
                    'nullable',
                    'string',
                    'max:100',
                    function ($attribute, $value, $fail) use ($data) {
                        $permission = trim((string)$value);
                        if ($permission === '') {
                            return;
                        }

                        $query = Menu::query()
                            ->where('type', 'B')
                            ->where('permission', $permission);

                        $excludeId = (int)($data['id'] ?? 0);
                        if ($excludeId > 0) {
                            $query->where('id', '<>', $excludeId);
                        }

                        $duplicate = $query->first();
                        if ($duplicate) {
                            $fail('已存在相同的权限标识「' . $permission . '」（菜单名称：' . $duplicate->name . '）');
                        }
                    },
                ],
            ];
        }

        return [];
    }

    /**
     * 构建菜单类型字段校验规则
     *
     * @param array $allRules
     * @return array
     */
    protected function buildMenuTypeFieldRules(array $allRules): array
    {
        $data = $this->data();
        $type = $data['type'] ?? '';
        $extraRules = [];

        switch ($type) {
            case 'D': // 目录
                $extraRules['component'] = ['nullable', 'string', 'max:200'];
                break;

            case 'M': // 菜单
                $extraRules['path'] = [
                    'required',
                    'string',
                    'max:200',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) {
                            $fail('菜单类型必须设置路由路径');
                        }
                    },
                ];
                $extraRules['component'] = [
                    'required',
                    'string',
                    'max:200',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) {
                            $fail('菜单类型必须设置组件路径');
                        }
                    },
                ];
                break;

            case 'B': // 按钮
                $extraRules['parent_id'] = [
                    'required',
                    'integer',
                    'min:1',
                    function ($attribute, $value, $fail) use ($data) {
                        if ((int)($data['parent_id'] ?? 0) <= 0) {
                            $fail('按钮节点必须选择父级菜单');
                        }
                    },
                ];
                $extraRules['permission'] = [
                    'required',
                    'string',
                    'max:100',
                    function ($attribute, $value, $fail) {
                        if (empty(trim((string)$value))) {
                            $fail('按钮类型必须设置权限标识');
                        }
                    },
                ];
                break;

            case 'L': // 外链
            case 'I': // 内嵌
                $extraRules['link'] = [
                    'required',
                    'string',
                    'max:500',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) {
                            $fail('外链/内嵌类型必须设置外链地址');
                        }
                    },
                ];
                break;
        }

        return $extraRules;
    }
}
