<?php

namespace plugin\nanoadmin\app\schema\role;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 角色数据权限请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 RoleValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\role\RoleValidator
 */
#[OA\Schema(title: '角色数据权限请求', description: '角色数据权限分配请求参数')]
class RoleDeptRequest extends RequestSchema
{
    #[OA\Property(description: '角色ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(
        description: '数据权限部门ID列表',
        type: 'array',
        items: new OA\Items(type: 'integer', format: 'int64'),
        example: [1, 2, 3]
    )]
    public array $deptIds = [];
}
