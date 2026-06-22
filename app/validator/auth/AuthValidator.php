<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\auth;

use plugin\nanoadmin\app\validator\ValidatorBaseWebman;

/**
 * 认证验证器
 *
 * 用于登录、刷新 Token、检查 Token 等接口的参数校验。
 *
 * 使用示例：
 * ```php
 * // 登录验证
 * $data = $validator->setScene('login')->setPost()->check();
 * ```
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
class AuthValidator extends ValidatorBaseWebman
{
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
                'max:50',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'password' => [
                'required',
                'string',
                'min:6',
                'max:50',
            ],
            'refresh_token' => [
                'required',
                'string',
                'min:10',
            ],
            'token' => [
                'required',
                'string',
                'min:10',
            ],
            'captcha_id' => [
                'nullable',
                'string',
            ],
            'captcha_code' => [
                'nullable',
                'string',
                'min:4',
                'max:6',
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
            'username.string' => '用户名必须是字符串',
            'username.min' => '用户名长度必须在3-50个字符之间',
            'username.max' => '用户名长度不能超过50个字符',
            'username.regex' => '用户名只能包含字母、数字和下划线',

            'password.required' => '密码不能为空',
            'password.string' => '密码必须是字符串',
            'password.min' => '密码长度必须在6-50个字符之间',
            'password.max' => '密码长度不能超过50个字符',

            'refresh_token.required' => '刷新Token不能为空',
            'refresh_token.string' => '刷新Token格式不正确',
            'refresh_token.min' => '刷新Token格式不正确',

            'token.required' => 'Token不能为空',
            'token.string' => 'Token格式不正确',
            'token.min' => 'Token格式不正确',

            'captcha_id.string' => '验证码ID必须是字符串',
            'captcha_code.string' => '验证码必须是字符串',
            'captcha_code.min' => '验证码长度必须在4-6个字符之间',
            'captcha_code.max' => '验证码长度不能超过6个字符',
        ];
    }

    /**
     * 场景定义
     */
    public function scenes(): array
    {
        return [
            'login' => ['username', 'password'],
            'refresh' => ['refresh_token'],
            'check' => ['token'],
        ];
    }
}
