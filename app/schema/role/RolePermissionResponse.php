<?php

namespace plugin\nanoadmin\app\schema\role;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 角色权限响应结构
 */
#[OA\Schema(title: '角色权限', description: '角色权限响应结构，包含菜单ID和权限编码')]
class RolePermissionResponse extends ResponseSchema
{
    #[OA\Property(description: '菜单ID列表', type: 'array', items: new OA\Items(type: 'integer', format: 'int64'), example: [1, 2, 3])]
    public array $menuIds = [];

    #[OA\Property(description: '权限编码列表', type: 'array', items: new OA\Items(type: 'string'), example: ['system:user:list', 'system:user:add'])]
    public array $authCodes = [];
}
