<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\log;

use plugin\nanoadmin\app\validator\ValidatorBase;
use support\validation\Rule;

/**
 * 登录日志验证器
 *
 * 使用 webman/validation（基于 illuminate/validation）
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
class LogLoginValidator extends ValidatorBase
{
    /**
     * 验证规则
     */
    public function rules(): array
    {
        return [
            'id' => [
                'nullable',
                'integer',
                'gt:0',
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
            'username' => [
                'nullable',
                'string',
                'max:50',
            ],
            'status' => [
                'nullable',
                'integer',
                Rule::in([0, 1]),
            ],
            'ip' => [
                'nullable',
                'string',
                'max:50',
            ],
            'start_time' => [
                'nullable',
                'date',
            ],
            'end_time' => [
                'nullable',
                'date',
                'after:start_time',
            ],
        ];
    }

    /**
     * 自定义消息
     */
    public function messages(): array
    {
        return [
            'id.integer' => 'ID必须是整数',
            'id.gt' => 'ID必须大于0',

            'current.integer' => '页码必须是整数',
            'current.min' => '页码必须大于0',

            'size.integer' => '每页数量必须是整数',
            'size.min' => '每页数量必须大于0',
            'size.max' => '每页数量不能超过100',

            'page.integer' => '页码必须是整数',
            'page.min' => '页码必须大于0',

            'limit.integer' => '每页数量必须是整数',
            'limit.min' => '每页数量必须大于0',
            'limit.max' => '每页数量不能超过100',

            'username.string' => '用户名必须是字符串',
            'username.max' => '用户名长度不能超过50个字符',

            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1',

            'ip.string' => 'IP地址必须是字符串',
            'ip.max' => 'IP地址长度不能超过50个字符',

            'start_time.date' => '开始时间格式不正确',
            'end_time.date' => '结束时间格式不正确',
            'end_time.after' => '结束时间必须大于开始时间',
        ];
    }

    /**
     * 场景定义
     */
    public function scenes(): array
    {
        return [
            'index' => ['current', 'size', 'username', 'status', 'ip', 'start_time', 'end_time'],
            'page' => ['page', 'limit', 'username', 'status', 'ip', 'start_time', 'end_time'],
            'show' => ['id'],
        ];
    }
}
