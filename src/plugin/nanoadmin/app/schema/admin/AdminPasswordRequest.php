<?php

namespace plugin\nanoadmin\app\schema\admin;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 修改密码请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 AdminValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\AdminValidator
 */
#[OA\Schema(title: '修改密码请求', description: '修改密码请求参数')]
class AdminPasswordRequest extends RequestSchema
{
    #[OA\Property(description: '旧密码', type: 'string', example: '******')]
    public string $old_password = '';

    #[OA\Property(description: '新密码', type: 'string', example: '******')]
    public string $password = '';

    #[OA\Property(description: '确认密码', type: 'string', example: '******')]
    public string $confirm_password = '';
}
