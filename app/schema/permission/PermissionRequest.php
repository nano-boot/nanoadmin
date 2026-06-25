<?php

namespace plugin\nanoadmin\app\schema\permission;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 权限创建/更新请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 PermissionValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\PermissionValidator
 */
#[OA\Schema(title: '权限请求', description: '权限创建/更新请求参数')]
class PermissionRequest extends RequestSchema
{
    #[OA\Property(description: '权限ID（更新时必填）', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '权限代码（如 user:create）', type: 'string', example: 'user:create')]
    public string $code = '';

    #[OA\Property(description: '权限名称', type: 'string', example: '创建用户')]
    public string $name = '';

    #[OA\Property(description: '资源类型（如 user）', type: 'string', example: 'user')]
    public string $resource_type = '';

    #[OA\Property(description: '操作类型（如 create）', type: 'string', example: 'create')]
    public string $action_type = '';

    #[OA\Property(description: '权限描述', type: 'string', example: '创建用户的权限')]
    public string $description = '';

    #[OA\Property(description: '排序值', type: 'integer', example: 0)]
    public int $sort = 0;

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}
