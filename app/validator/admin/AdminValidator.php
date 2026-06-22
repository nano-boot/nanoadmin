<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\admin;

use plugin\nanoadmin\app\model\Admin;
use plugin\nanoadmin\app\validator\ValidatorBaseWebman;
use support\validation\Rule;

/**
 * 管理员验证器
 *
 * 使用示例：
 * ```php
 * // 方式1：控制器中（使用请求数据）
 * $data = $validator->setScene('create')->setPost()->check();
 *
 * // 方式2：传入自定义数据（check 支持接收数据参数）
 * $data = $validator->setScene('create')->check($customData);
 *
 * // 方式3：带上下文（排除自身）
 * $data = $validator->withContext(['excludeId' => $id])->setScene('update')->setPost()->check();
 * ```
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
class AdminValidator extends ValidatorBaseWebman
{
    /**
     * 模型类（用于 unique/exists 规则自动解析表名）
     *
     * @var string|null
     */
    protected ?string $model = Admin::class;

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
            'username' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[a-zA-Z0-9_]+$/',
                $this->unique('username'),
            ],
            'password' => [
                'nullable',
                'string',
                'min:6',
                'max:20',
            ],
            'old_password' => [
                'required',
                'string',
                'min:6',
                'max:20',
            ],
            'confirm_password' => [
                'required',
                'string',
                'min:6',
                'max:20',
                'same:password',
            ],
            'nickname' => [
                'nullable',
                'string',
                'min:2',
                'max:50',
            ],
            'phone' => [
                'nullable',
                'string',
                'regex:/^1[3-9]\d{9}$/',
                $this->unique('phone'),
            ],
            'email' => [
                'nullable',
                'email',
                'max:100',
                $this->unique('email'),
            ],
            'avatar' => [
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'nullable',
                'integer',
                Rule::in([0, 1]),
            ],
            'gender' => [
                'nullable',
                'integer',
                Rule::in([0, 1, 2]),
            ],
            'role_ids' => [
                'nullable',
                'array',
            ],
            'role_ids.*' => [
                'integer',
                'gt:0',
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
            'id' => [
                'required',
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
        ];
    }

    /**
     * 自定义消息
     */
    public function messages(): array
    {
        return [
            'username.required' => '用户名不能为空',
            'username.min' => '用户名长度必须在3-20个字符之间',
            'username.max' => '用户名长度必须在3-20个字符之间',
            'username.regex' => '用户名只能包含字母、数字和下划线',
            'username.unique' => '用户名已存在',

            'password.min' => '密码长度必须在6-20个字符之间',
            'password.max' => '密码长度必须在6-20个字符之间',

            'old_password.required' => '旧密码不能为空',
            'old_password.min' => '旧密码长度必须在6-20个字符之间',
            'old_password.max' => '旧密码长度必须在6-20个字符之间',

            'confirm_password.required' => '确认密码不能为空',
            'confirm_password.same' => '确认密码与密码不一致',

            'nickname.min' => '昵称长度必须在2-50个字符之间',
            'nickname.max' => '昵称长度必须在2-50个字符之间',

            'phone.regex' => '手机号格式不正确',
            'phone.unique' => '手机号已存在',

            'email.email' => '邮箱格式不正确',
            'email.max' => '邮箱长度不能超过100个字符',
            'email.unique' => '邮箱已存在',

            'avatar.max' => '头像URL长度不能超过255个字符',

            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1',

            'gender.integer' => '性别必须是整数',
            'gender.in' => '性别值只能是0、1或2',

            'role_ids.array' => '角色ID必须是数组',
            'role_ids.*.integer' => '角色ID必须是整数',
            'role_ids.*.gt' => '角色ID必须大于0',

            'ids.required' => 'ID数组不能为空',
            'ids.array' => 'ID必须是数组',
            'ids.min' => '至少选择一个项目',
            'ids.*.integer' => 'ID必须是整数',
            'ids.*.gt' => 'ID必须大于0',

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
        ];
    }

    /**
     * 场景定义
     */
    public function scenes(): array
    {
        return [
            'login' => ['username', 'password'],

            'create' => [
                'username',
                'password',
                'nickname',
                'phone',
                'email',
                'avatar',
                'status',
                'gender',
            ],

            'update' => [
                'id',
                'username',
                'password',
                'nickname',
                'phone',
                'email',
                'avatar',
                'status',
                'gender',
                'role_ids',
            ],

            'assignRoles' => ['role_ids'],

            'batchDelete' => ['ids'],

            'show' => ['id'],

            'delete' => ['id'],

            'page' => ['page', 'limit', 'keyword'],

            'updatePassword' => ['id', 'password'],

            'updateProfile' => ['nickname', 'phone', 'email', 'avatar', 'gender'],

            'updateCurrentPassword' => ['old_password', 'password', 'confirm_password'],
        ];
    }
}
