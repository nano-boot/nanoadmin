<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\role;

use plugin\nanoadmin\app\model\Role;
use plugin\nanoadmin\app\validator\ValidatorBase;
use support\validation\Rule;

/**
 * 角色验证器
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
class RoleValidator extends ValidatorBase
{
    /**
     * 模型类（用于 unique/exists 规则自动解析表名）
     *
     * @var string|null
     */
    protected ?string $model = Role::class;

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
                'min:2',
                'max:50',
                $this->unique('name'),
            ],
            'code' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-zA-Z0-9_-]+$/',
                $this->unique('code'),
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
                Rule::in([0, 1]),
            ],
            'role_ids' => [
                'nullable',
                'array',
            ],
            'role_ids.*' => [
                'integer',
                'gt:0',
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
            'status_filter' => [
                'nullable',
                'integer',
                Rule::in([0, 1]),
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

            'name.required' => '角色名称不能为空',
            'name.string' => '角色名称必须是字符串',
            'name.min' => '角色名称长度必须在2-50个字符之间',
            'name.max' => '角色名称长度不能超过50个字符',
            'name.unique' => '角色名称已存在',

            'code.required' => '角色编码不能为空',
            'code.string' => '角色编码必须是字符串',
            'code.min' => '角色编码长度必须在2-50个字符之间',
            'code.max' => '角色编码长度不能超过50个字符',
            'code.regex' => '角色编码只能包含字母、数字、下划线和短横线',
            'code.unique' => '角色编码已存在',

            'description.string' => '角色描述必须是字符串',
            'description.max' => '角色描述长度不能超过255个字符',

            'sort.integer' => '角色排序必须是整数',
            'sort.min' => '角色排序必须大于等于0',
            'sort.max' => '角色排序必须小于等于9999',

            'status.integer' => '角色状态必须是整数',
            'status.in' => '角色状态值只能是0或1',

            'role_ids.array' => '角色ID列表必须是数组',
            'role_ids.*.integer' => '角色ID必须是整数',
            'role_ids.*.gt' => '角色ID必须大于0',

            'page.integer' => '页码必须是整数',
            'page.min' => '页码必须大于0',

            'limit.integer' => '每页数量必须是整数',
            'limit.min' => '每页数量必须大于0',
            'limit.max' => '每页数量不能超过100',

            'keyword.string' => '关键词必须是字符串',
            'keyword.max' => '关键词长度不能超过100个字符',

            'status_filter.integer' => '状态过滤必须是整数',
            'status_filter.in' => '状态过滤值只能是0或1',

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
            'store' => [
                'name',
                'code',
                'description',
                'sort',
                'status',
            ],

            'update' => [
                'id',
                'name',
                'code',
                'description',
                'sort',
                'status',
            ],

            'assignRoles' => ['id', 'role_ids'],

            'destroy' => ['id'],
            'batchDestroy' => ['ids'],
            'show' => ['id'],
            'index' => ['page', 'limit', 'keyword', 'status_filter'],
        ];
    }
}
