<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\config;

use plugin\nanoadmin\app\validator\ValidatorBaseWebman;
use plugin\nanoadmin\app\validator\traits\UpdateUniqueTrait;
use support\validation\Rule as IlluminateRule;

/**
 * 系统配置验证器
 *
 * 使用 webman/validation（基于 illuminate/validation）
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
class ConfigValidator extends ValidatorBaseWebman
{
    use UpdateUniqueTrait {
        buildUpdateUnique as protected _buildUpdateUnique;
    }

    /**
     * 表名
     */
    protected string $table = 'sys_config';

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
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_]+$/',
                IlluminateRule::unique($this->table, 'key'),
            ],
            'value' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'type' => [
                'required',
                'string',
                IlluminateRule::in(['text', 'number', 'boolean', 'select', 'radio', 'checkbox', 'textarea', 'json']),
            ],
            'options' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'group' => [
                'required',
                'string',
                'max:50',
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
            'sort' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999',
            ],
            'status' => [
                'nullable',
                'integer',
                IlluminateRule::in([0, 1]),
            ],
            'current' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'size' => [
                'nullable',
                'integer',
                'min:1',
                'max:200',
            ],
            'keyword' => [
                'nullable',
                'string',
                'max:100',
            ],
            'group_filter' => [
                'nullable',
                'string',
                'max:50',
            ],
            'type_filter' => [
                'nullable',
                'string',
                IlluminateRule::in(['text', 'number', 'boolean', 'select', 'radio', 'checkbox', 'textarea', 'json']),
            ],
            'status_filter' => [
                'nullable',
                'integer',
                IlluminateRule::in([0, 1]),
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
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'items.*.value' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * 自定义消息
     */
    public function messages(): array
    {
        return [
            'id.required' => '配置ID不能为空',
            'id.integer' => '配置ID必须为整数',
            'id.gt' => '配置ID必须大于0',

            'name.required' => '请输入配置名称',
            'name.string' => '配置名称必须是字符串',
            'name.max' => '配置名称最多100个字符',

            'key.required' => '请输入配置键名',
            'key.string' => '配置键名必须是字符串',
            'key.max' => '配置键名最多100个字符',
            'key.regex' => '配置键名只能包含字母、数字和下划线',
            'key.unique' => '配置键名已存在',

            'value.string' => '配置值必须是字符串',
            'value.max' => '配置值最多2000个字符',

            'type.required' => '请选择配置类型',
            'type.string' => '配置类型必须是字符串',
            'type.in' => '配置类型不正确',

            'options.string' => '选项配置必须是字符串',
            'options.max' => '选项配置最多1000个字符',

            'group.required' => '请选择配置分组',
            'group.string' => '配置分组必须是字符串',
            'group.max' => '配置分组最多50个字符',

            'description.string' => '配置描述必须是字符串',
            'description.max' => '配置描述最多500个字符',

            'sort.integer' => '排序必须是整数',
            'sort.min' => '排序必须大于等于0',
            'sort.max' => '排序必须小于等于999999',

            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1',

            'current.integer' => '页码必须是整数',
            'current.min' => '页码必须大于0',

            'size.integer' => '每页数量必须是整数',
            'size.min' => '每页数量必须大于0',
            'size.max' => '每页数量不能超过200',

            'keyword.string' => '关键词必须是字符串',
            'keyword.max' => '关键词长度不能超过100个字符',

            'group_filter.string' => '分组过滤必须是字符串',
            'group_filter.max' => '分组过滤最多50个字符',

            'type_filter.string' => '类型过滤必须是字符串',
            'type_filter.in' => '类型过滤值不正确',

            'status_filter.integer' => '状态过滤必须是整数',
            'status_filter.in' => '状态过滤值只能是0或1',

            'ids.required' => '请选择要删除的配置',
            'ids.array' => '配置ID列表格式错误',
            'ids.min' => '至少选择一个配置',
            'ids.*.integer' => 'ID必须是整数',
            'ids.*.gt' => 'ID必须大于0',

            'items.required' => '请提交配置项列表',
            'items.array' => '配置项必须为数组',
            'items.min' => '至少提交一个配置项',

            'items.*.key.required' => '配置项键名不能为空',
            'items.*.key.string' => '配置项键名必须是字符串',
            'items.*.key.max' => '配置项键名最多100个字符',
            'items.*.key.regex' => '配置项键名只能包含字母、数字和下划线',

            'items.*.value.string' => '配置项值必须是字符串',
            'items.*.value.max' => '配置项值最多2000个字符',
        ];
    }

    /**
     * 场景定义
     */
    public function scenes(): array
    {
        return [
            'store' => [
                'name',
                'key',
                'type',
                'group',
                'value',
                'options',
                'description',
                'sort',
                'status',
            ],

            'update' => function (array $allRules): array {
                return $this->buildUpdateUnique($allRules, ['key']);
            },

            'index' => ['current', 'size', 'keyword', 'group_filter', 'type_filter', 'status_filter'],
            'page' => ['current', 'size', 'keyword', 'group_filter', 'type_filter', 'status_filter'],

            'show' => ['id'],
            'destroy' => ['id'],
            'batch_destroy' => ['ids'],
            'batch_update' => ['items'],
            'get_by_group' => ['group'],
        ];
    }

    /**
     * 验证ID
     */
    public function validateId($id): int
    {
        $data = $this->validateData(['id' => $id], 'show');
        return (int)$data['id'];
    }

    /**
     * 验证批量ID
     */
    public function validateBatchIds(array $data): array
    {
        return $this->validateData($data, 'batch_destroy');
    }
}
