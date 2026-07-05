<?php

namespace plugin\nanoadmin\app\schema\auth;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 管理员登录请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 AuthValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\AuthValidator
 */
#[OA\Schema(title: '管理员登录', description: '管理员登录请求参数')]
class LoginRequest extends RequestSchema
{
    #[OA\Property(description: '用户名', type: 'string', example: 'admin')]
    public string $username = '';

    #[OA\Property(description: '密码', type: 'string', example: '123456')]
    public string $password = '';
}
