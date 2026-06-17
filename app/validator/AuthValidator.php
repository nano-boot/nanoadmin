<?php

namespace plugin\nanoadmin\app\validator;

use plugin\nanoadmin\app\common\ApiException;

/**
 * 认证相关验证器
 *
 * 用于登录、刷新 Token、检查 Token 等接口的参数校验。
 * 校验规则由控制器手动调用 validateXxxData() 触发。
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
class AuthValidator extends ValidatorBase
{
    /**
     * 校验规则
     * @var array
     */
    protected $rule = [
        'username'      => 'require|string|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
        'password'      => 'require|string|min:6|max:50',
        'refresh_token' => 'require|string|min:10',
        'token'         => 'require|string|min:10',
    ];

    /**
     * 校验消息
     * @var array
     */
    protected $message = [
        'username.required' => '用户名不能为空',
        'username.string'   => '用户名必须是字符串',
        'username.min'      => '用户名长度必须在3-50个字符之间',
        'username.max'      => '用户名长度必须在3-50个字符之间',
        'username.regex'    => '用户名只能包含字母、数字和下划线',

        'password.required' => '密码不能为空',
        'password.string'   => '密码必须是字符串',
        'password.min'      => '密码长度必须在6-50个字符之间',
        'password.max'      => '密码长度必须在6-50个字符之间',

        'refresh_token.required' => '刷新Token不能为空',
        'refresh_token.string'   => '刷新Token格式不正确',
        'refresh_token.min'      => '刷新Token格式不正确',

        'token.required' => 'Token不能为空',
        'token.string'   => 'Token格式不正确',
        'token.min'      => 'Token格式不正确',
    ];

    /**
     * 校验场景
     *
     * 场景命名对齐控制器方法名，便于配合 ValidatorBase::validated()。
     * @var array
     */
    protected $scene = [
        'login'   => ['username', 'password'],
        'refresh' => ['refresh_token'],
        'check'   => ['token'],
    ];

    /**
     * 验证登录参数
     *
     * @param array $data 原始请求数据
     * @return array 验证后的数据
     * @throws ApiException
     */
    public function validateLoginData(array $data): array
    {
        return $this->validateData($data, 'login');
    }

    /**
     * 验证刷新 Token 参数
     *
     * @param array $data 原始请求数据
     * @return array
     * @throws ApiException
     */
    public function validateRefreshData(array $data): array
    {
        return $this->validateData($data, 'refresh');
    }

    /**
     * 验证检查 Token 参数
     *
     * @param array $data 原始请求数据
     * @return array
     * @throws ApiException
     */
    public function validateCheckData(array $data): array
    {
        return $this->validateData($data, 'check');
    }
}
