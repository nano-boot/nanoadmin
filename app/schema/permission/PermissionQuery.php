<?php

namespace plugin\nanoadmin\app\schema\permission;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 权限查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 PermissionValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\PermissionValidator
 */
#[OA\Schema(title: '权限查询', description: '权限列表查询参数')]
class PermissionQuery extends QuerySchema
{
    #[OA\Property(description: '页码', type: 'integer', example: 1)]
    public int $page = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $limit = 20;

    #[OA\Property(description: '关键词（权限名称/代码）', type: 'string', example: 'admin')]
    public string $keyword = '';

    #[OA\Property(description: '资源类型', type: 'string', example: 'user')]
    public string $resource_type = '';

    #[OA\Property(description: '操作类型', type: 'string', example: 'create')]
    public string $action_type = '';
}
