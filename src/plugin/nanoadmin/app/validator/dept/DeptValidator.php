<?php

namespace plugin\nanoadmin\app\validator\dept;

use plugin\nanoadmin\app\model\Dept;
use plugin\nanoadmin\app\validator\ValidatorBase;
use support\validation\Rule;

/**
 * 部门验证器
 */
class DeptValidator extends ValidatorBase
{
    /**
     * 模型类
     * @var string|null
     */
    protected ?string $model = Dept::class;

    /**
     * 主键字段
     */
    protected string $primaryKey = 'id';

    /**
     * 验证规则
     *
     * 规则按"全场景兼容"原则编写：默认用 `nullable` 让字段在所有场景都可选。
     * store / update 场景需要 `name` 必填，在 `rules()` 内根据 `_scene` 动态收紧。
     */
    public function rules(): array
    {
        $rules = [
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
                'nullable',
                'string',
                'min:1',
                'max:100',
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:100',
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
                Rule::in([0, 1]),
            ],
            'keyword' => [
                'nullable',
                'string',
                'max:100',
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
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'integer',
                'gt:0',
            ],
            'only_enabled' => [
                'nullable',
                'boolean',
            ],
        ];

        // store / update 场景必须传部门名称
        if (in_array($this->_scene, ['store', 'update'], true)) {
            $rules['name'] = [
                'required',
                'string',
                'min:1',
                'max:100',
            ];
        }

        // 未绑定场景时返回全部规则（兼容老调用）
        if ($this->_scene === null) {
            return $rules;
        }

        // 按场景过滤字段
        $sceneFields = $this->getScenes()[$this->_scene] ?? [];
        if (empty($sceneFields)) {
            return $rules;
        }

        return array_intersect_key($rules, array_flip($sceneFields));
    }

    /**
     * 自定义消息
     */
    public function messages(): array
    {
        return [
            'id.required' => '部门ID不能为空',
            'id.integer' => '部门ID必须是整数',
            'id.gt' => '部门ID必须大于0',

            'parent_id.integer' => '父部门ID必须是整数',
            'parent_id.min' => '父部门ID必须大于等于0',

            'name.required' => '部门名称不能为空',
            'name.string' => '部门名称必须是字符串',
            'name.min' => '部门名称长度至少1个字符',
            'name.max' => '部门名称长度不能超过100个字符',

            'code.string' => '部门编码必须是字符串',
            'code.max' => '部门编码长度不能超过50个字符',

            'phone.string' => '联系电话必须是字符串',
            'phone.max' => '联系电话长度不能超过20个字符',

            'email.string' => '邮箱必须是字符串',
            'email.email' => '邮箱格式不正确',
            'email.max' => '邮箱长度不能超过100个字符',

            'sort.integer' => '排序必须是整数',
            'sort.min' => '排序必须大于等于0',
            'sort.max' => '排序必须小于等于9999',

            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1',

            'keyword.string' => '关键词必须是字符串',
            'keyword.max' => '关键词长度不能超过100个字符',

            'page.integer' => '页码必须是整数',
            'page.min' => '页码必须大于0',

            'limit.integer' => '每页数量必须是整数',
            'limit.min' => '每页数量必须大于0',
            'limit.max' => '每页数量不能超过100',

            'ids.required' => 'ID数组不能为空',
            'ids.array' => 'ID必须是数组',
            'ids.min' => '至少选择一个部门',
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
            'store' => [
                'name',
                'code',
                'parent_id',
                'phone',
                'email',
                'sort',
                'status',
            ],

            'update' => [
                'id',
                'name',
                'code',
                'parent_id',
                'phone',
                'email',
                'sort',
                'status',
            ],

            'show' => ['id'],
            'destroy' => ['id'],
            'batchDestroy' => ['ids'],
            'page' => ['page', 'limit', 'keyword', 'status', 'parent_id'],

            'tree' => [
                'parent_id',
                'keyword',
                'status',
                'only_enabled',
                'name',
                'code',
            ],
        ];
    }
}
