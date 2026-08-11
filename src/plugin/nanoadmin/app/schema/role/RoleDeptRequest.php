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
#[OA\Schema(title: '角色数据权限请求', description: '角色数据权限分配请求参数（含数据权限范围与可选的自定义部门列表）')]
class RoleDeptRequest extends RequestSchema
{
    #[OA\Property(description: '角色ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(
        description: '数据权限范围（1全部数据 2本部门及下级 3本部门 4仅本人 5自定义部门）',
        type: 'integer',
        example: 5
    )]
    public int $dataScope = 1;

    #[OA\Property(
        description: '自定义部门ID列表（dataScope=5 时生效）',
        type: 'array',
        items: new OA\Items(type: 'integer', format: 'int64'),
        example: [1, 2, 3]
    )]
    public array $deptIds = [];
}