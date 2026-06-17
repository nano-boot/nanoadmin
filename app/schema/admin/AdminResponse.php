<?php

namespace plugin\nanoadmin\app\schema\admin;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 管理员响应结构
 */
#[OA\Schema(title: '管理员', description: '管理员响应结构')]
class AdminResponse extends ResponseSchema
{
    #[OA\Property(description: 'ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '用户名', type: 'string', example: 'admin')]
    public string $username = '';

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

    #[OA\Property(description: '角色列表', type: 'array', items: new OA\Items(type: 'string'), example: ['管理员'])]
    public array $roles = [];

    #[OA\Property(description: '创建时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $create_time = '';

    #[OA\Property(description: '更新时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $update_time = '';
}
