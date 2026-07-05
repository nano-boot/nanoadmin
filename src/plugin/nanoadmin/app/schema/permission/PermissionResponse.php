<?php

namespace plugin\nanoadmin\app\schema\permission;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 权限响应结构
 */
#[OA\Schema(title: '权限', description: '权限响应结构')]
class PermissionResponse extends ResponseSchema
{
    #[OA\Property(description: 'ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '权限代码', type: 'string', example: 'user:create')]
    public string $code = '';

    #[OA\Property(description: '权限名称', type: 'string', example: '创建用户')]
    public string $name = '';

    #[OA\Property(description: '资源类型', type: 'string', example: 'user')]
    public string $resource_type = '';

    #[OA\Property(description: '操作类型', type: 'string', example: 'create')]
    public string $action_type = '';

    #[OA\Property(description: '权限描述', type: 'string', example: '创建用户的权限')]
    public string $description = '';

    #[OA\Property(description: '排序值', type: 'integer', example: 0)]
    public int $sort = 0;

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;

    #[OA\Property(description: '创建时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $create_time = '';

    #[OA\Property(description: '更新时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $update_time = '';
}
