<?php

namespace plugin\nanoadmin\app\schema\role;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 角色数据权限响应结构
 */
#[OA\Schema(title: '角色数据权限', description: '角色数据权限响应结构（含范围与已分配的自定义部门列表）')]
class RoleDeptResponse extends ResponseSchema
{
    #[OA\Property(description: '角色ID', type: 'integer', format: 'int64', example: 1)]
    public int $roleId = 0;

    #[OA\Property(
        description: '数据权限范围（1全部数据 2本部门及下级 3本部门 4仅本人 5自定义部门）',
        type: 'integer',
        example: 5
    )]
    public int $dataScope = 1;

    #[OA\Property(
        description: '数据权限部门ID列表',
        type: 'array',
        items: new OA\Items(type: 'integer', format: 'int64'),
        example: [1, 2, 3]
    )]
    public array $deptIds = [];

    #[OA\Property(
        description: '数据权限部门列表（完整对象）',
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/部门')
    )]
    public array $depts = [];
}