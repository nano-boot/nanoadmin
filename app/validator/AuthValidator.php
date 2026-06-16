<?php

namespace plugin\nanoadmin\app\validator;

use think\Validate;

/**
 * 管理员验证器
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 *
 */
class AuthValidator extends ValidatorBase
{
    protected $rule = [
        'username' => 'require|string|min:3|max:20|regex:/^[a-zA-Z0-9_]+$/',
        'password' => 'require|string|min:6|max:20',
    ];

    protected $message = [
        'username.required' => '用户名不能为空',
        'username.min' => '用户名长度必须在3-20个字符之间',
        'username.max' => '用户名长度必须在3-20个字符之间',
        'username.regex' => '用户名只能包含字母、数字和下划线',
        'username.unique' => '用户名已存在',
        'password.required' => '密码不能为空',
        'password.min' => '密码长度必须在6-20个字符之间',
        'password.max' => '密码长度必须在6-20个字符之间',
    ];

    protected $scene = [
        'login'  =>  ['username','password'],
    ];
}