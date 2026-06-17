<?php

namespace plugin\nanoadmin\app\schema\admin;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 管理员创建/更新请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 AdminValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\AdminValidator
 */
#[OA\Schema(title: '管理员请求', description: '管理员创建/更新请求参数')]
class AdminRequest extends RequestSchema
{
    #[OA\Property(description: '管理员ID（更新时必填）', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '用户名', type: 'string', example: 'admin')]
    public string $username = '';

    #[OA\Property(description: '密码（创建时必填，更新时可选）', type: 'string', example: '******')]
    public string $password = '';

    #[OA\Property(description: '昵称', type: 'string', example: '管理员')]
    public string $nickname = '';

    #[OA\Property(description: '手机号', type: 'string', example: '13800138000')]
    public string $phone = '';

    #[OA\Property(description: '邮箱', type: 'string', example: 'admin@example.com')]
    public string $email = '';

    #[OA\Property(description: '头像URL', type: 'string', example: 'https://example.com/avatar.png')]
    public string $avatar = '';

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;

    #[OA\Property(description: '性别（0未知 1男 2女）', type: 'integer', example: 1)]
    public int $gender = 0;
}
