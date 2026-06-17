<?php

namespace plugin\nanoadmin\app\schema\auth;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 权限码项（用于 permissions / menus 接口列表元素）
 */
#[OA\Schema(title: '权限码', description: '单个权限码对象')]
class PermissionItem extends ResponseSchema
{
    #[OA\Property(description: '权限ID', type: 'integer', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '权限名称', type: 'string', example: '查看管理员')]
    public string $name = '';

    #[OA\Property(description: '权限码', type: 'string', example: 'admin:view')]
    public string $code = '';

    #[OA\Property(description: '权限类型（D目录 M菜单 B按钮）', type: 'string', example: 'B')]
    public string $type = 'B';

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}
