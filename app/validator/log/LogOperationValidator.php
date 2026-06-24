<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\log;

use plugin\nanoadmin\app\validator\ValidatorBase;
use support\validation\Rule;

/**
 * 操作日志验证器
 *
 * 使用 webman/validation（基于 illuminate/validation）
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
class LogOperationValidator extends ValidatorBase
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
            'module' => [
                'nullable',
                'string',
                'max:50',
            ],
            'action' => [
                'nullable',
                'string',
                'max:50',
            ],
            'ip' => [
                'nullable',
                'string',
                'max:50',
            ],
            'request_method' => [
                'nullable',
                'string',
                'in:GET,POST,PUT,DELETE,PATCH,HEAD,OPTIONS',
            ],
            'http_status' => [
                'nullable',
                'integer',
                'between:100,599',
            ],
            'response_code' => [
                'nullable',
                'integer',
            ],
            'keyword' => [
                'nullable',
                'string',
                'max:100',
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

            'module.string' => '模块必须是字符串',
            'module.max' => '模块长度不能超过50个字符',

            'action.string' => '操作类型必须是字符串',
            'action.max' => '操作类型长度不能超过50个字符',

            'ip.string' => 'IP地址必须是字符串',
            'ip.max' => 'IP地址长度不能超过50个字符',

            'request_method.string' => '请求方法必须是字符串',
            'request_method.in' => '请求方法取值不正确',

            'http_status.integer' => 'HTTP状态码必须是整数',
            'http_status.between' => 'HTTP状态码取值范围不正确',

            'response_code.integer' => '业务状态码必须是整数',

            'keyword.string' => '关键词必须是字符串',
            'keyword.max' => '关键词长度不能超过100个字符',

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
            'index' => ['current', 'size', 'username', 'module', 'action', 'ip', 'request_method', 'http_status', 'response_code', 'keyword', 'start_time', 'end_time'],
            'page' => ['page', 'limit', 'username', 'module', 'action', 'ip', 'request_method', 'http_status', 'response_code', 'keyword', 'start_time', 'end_time'],
            'show' => ['id'],
        ];
    }
}
