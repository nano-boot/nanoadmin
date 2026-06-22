<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\dict;

use plugin\nanoadmin\app\validator\ValidatorBaseWebman;
use plugin\nanoadmin\app\validator\traits\UpdateUniqueTrait;
use support\validation\Rule as IlluminateRule;

/**
 * 字典类型验证器
 *
 * 使用 webman/validation（基于 illuminate/validation）
 */
class DictTypeValidator extends ValidatorBaseWebman
{
    use UpdateUniqueTrait {
        buildUpdateUnique as protected _buildUpdateUnique;
    }

    /**
     * 表名
     */
    protected string $table = 'sys_dict_type';

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
                'min:1',
                'max:100',
                IlluminateRule::unique($this->table, 'name'),
            ],
            'code' => [
                'required',
                'string',
                'min:1',
                'max:100',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                IlluminateRule::unique($this->table, 'code'),
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'sort' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
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
        ];
    }

    /**
     * 自定义消息
     */
    public function messages(): array
    {
        return [
            'id.required' => 'ID不能为空',
            'id.integer' => 'ID必须是整数',
            'id.gt' => 'ID必须大于0',

            'name.required' => '字典名称不能为空',
            'name.string' => '字典名称必须是字符串',
            'name.min' => '字典名称长度至少1个字符',
            'name.max' => '字典名称长度不能超过100个字符',

            'code.required' => '字典编码不能为空',
            'code.string' => '字典编码必须是字符串',
            'code.min' => '字典编码长度至少1个字符',
            'code.max' => '字典编码长度不能超过100个字符',
            'code.regex' => '字典编码必须以字母开头，只能包含字母、数字和下划线',

            'description.string' => '字典描述必须是字符串',
            'description.max' => '字典描述长度不能超过255个字符',

            'sort.integer' => '排序必须是整数',
            'sort.min' => '排序必须大于等于0',
            'sort.max' => '排序必须小于等于9999',

            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1',

            'current.integer' => '页码必须是整数',
            'current.min' => '页码必须大于0',

            'size.integer' => '每页数量必须是整数',
            'size.min' => '每页数量必须大于0',
            'size.max' => '每页数量不能超过100',

            'keyword.string' => '关键词必须是字符串',
            'keyword.max' => '关键词长度不能超过100个字符',

            'ids.required' => 'ID数组不能为空',
            'ids.array' => 'ID必须是数组',
            'ids.min' => '至少选择一个项目',
            'ids.*.integer' => 'ID必须是整数',
            'ids.*.gt' => 'ID必须大于0',
        ];
    }

    /**
     * 场景定义
     */
    public function scenes(): array
    {
        return [
            'page' => [
                'current',
                'size',
                'keyword',
                'status',
            ],
            'store' => [
                'name',
                'code',
                'description',
                'sort',
                'status',
            ],
            'update' => function (array $allRules, array $context = []): array {
                return $this->buildUpdateUnique($allRules, ['name', 'code'], [
                    'fields' => ['name', 'code', 'description', 'sort', 'status'],
                    'excludeId' => $context['excludeId'] ?? 0,
                ]);
            },
            'show' => ['id'],
            'destroy' => ['id'],
            'batch_delete' => ['ids'],
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
        return $this->validateData($data, 'batch_delete');
    }
}