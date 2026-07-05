<?php

namespace plugin\nanoadmin\app\schema\role;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 角色响应结构
 */
#[OA\Schema(title: '角色', description: '角色响应结构')]
class RoleResponse extends ResponseSchema
{
    #[OA\Property(description: 'ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '角色名称', type: 'string', example: '管理员')]
    public string $name = '';

    #[OA\Property(description: '角色编码', type: 'string', example: 'admin')]
    public string $code = '';

    #[OA\Property(description: '角色描述', type: 'string', example: '系统管理员角色')]
    public string $description = '';

    #[OA\Property(description: '排序值', type: 'integer', example: 100)]
    public int $sort = 0;

    #[OA\Property(description: '关联用户数', type: 'integer', example: 5)]
    public int $userCount = 0;

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;

    #[OA\Property(description: '创建时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $create_time = '';

    #[OA\Property(description: '更新时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $update_time = '';
}
