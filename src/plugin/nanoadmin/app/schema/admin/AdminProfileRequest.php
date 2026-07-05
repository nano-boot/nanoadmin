<?php

namespace plugin\nanoadmin\app\schema\admin;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 更新个人资料请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 AdminValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\AdminValidator
 */
#[OA\Schema(title: '更新个人资料请求', description: '更新当前用户资料请求参数')]
class AdminProfileRequest extends RequestSchema
{
    #[OA\Property(description: '昵称', type: 'string', example: '新昵称')]
    public string $nickname = '';

    #[OA\Property(description: '手机号', type: 'string', example: '13800138000')]
    public string $phone = '';

    #[OA\Property(description: '邮箱', type: 'string', example: 'new@example.com')]
    public string $email = '';

    #[OA\Property(description: '头像URL', type: 'string', example: 'https://example.com/new-avatar.png')]
    public string $avatar = '';

    #[OA\Property(description: '性别（0未知 1男 2女）', type: 'integer', example: 1)]
    public int $gender = 0;
}
