<?php

namespace plugin\nanoadmin\app\schema\role;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 角色查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 RoleValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\RoleValidator
 */
#[OA\Schema(title: '角色查询', description: '角色列表查询参数')]
class RoleQuery extends QuerySchema
{
    #[OA\Property(description: '页码', type: 'integer', example: 1)]
    public int $current = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $size = 20;

    #[OA\Property(description: '角色名称（模糊查询）', type: 'string', example: '管理员')]
    public string $name = '';

    #[OA\Property(description: '角色编码（模糊查询）', type: 'string', example: 'admin')]
    public string $code = '';

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}
