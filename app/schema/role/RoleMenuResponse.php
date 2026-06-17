<?php

namespace plugin\nanoadmin\app\schema\role;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 角色菜单响应结构
 */
#[OA\Schema(title: '角色菜单', description: '角色菜单列表响应结构')]
class RoleMenuResponse extends ResponseSchema
{
    #[OA\Property(description: 'ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '菜单ID', type: 'integer', format: 'int64', example: 1)]
    public int $menu_id = 0;

    #[OA\Property(description: '菜单标题', type: 'string', example: '系统管理')]
    public string $title = '';

    #[OA\Property(description: '菜单类型（D目录 M菜单 B按钮）', type: 'string', example: 'M')]
    public string $type = '';

    #[OA\Property(description: '父级ID', type: 'integer', format: 'int64', example: 0)]
    public int $parent_id = 0;

    #[OA\Property(description: '菜单路径', type: 'string', example: '/system/user')]
    public string $path = '';

    #[OA\Property(description: '菜单图标', type: 'string', example: 'user')]
    public string $icon = '';
}
