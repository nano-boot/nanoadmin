<?php

namespace plugin\nanoadmin\app\validator;

/**
 * 登录日志验证器
 */
class LogLoginValidator extends ValidatorBase
{
    protected function rules(): array
    {
        return [
            'current' => 'integer|min:1',
            'size' => 'integer|min:1|max:100',
            'username' => 'string|max:50',
            'status' => 'integer|in:0,1',
            'start_time' => 'dateFormat:Y-m-d H:i:s',
            'end_time' => 'dateFormat:Y-m-d H:i:s',
        ];
    }

    protected $message = [
        'current.integer' => '页码必须是整数',
        'current.min' => '页码必须大于0',
        'size.integer' => '每页数量必须是整数',
        'size.min' => '每页数量必须大于0',
        'size.max' => '每页数量不能超过100',
        'username.string' => '用户名必须是字符串',
        'username.max' => '用户名长度不能超过50个字符',
        'status.integer' => '状态必须是整数',
        'status.in' => '状态值只能是0或1',
        'start_time.dateFormat' => '开始时间格式不正确',
        'end_time.dateFormat' => '结束时间格式不正确',
    ];

    protected $scene = [
        'index' => ['current', 'size', 'username', 'status', 'start_time', 'end_time'],
    ];
}
