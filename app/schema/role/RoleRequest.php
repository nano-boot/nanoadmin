<?php

namespace plugin\nanoadmin\app\schema\role;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 角色创建/更新请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 RoleValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\RoleValidator
 */
#[OA\Schema(title: '角色请求', description: '角色创建/更新请求参数')]
class RoleRequest extends RequestSchema
{
    #[OA\Property(description: '角色ID（更新时必填）', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '角色名称', type: 'string', example: '管理员')]
    public string $name = '';

    #[OA\Property(description: '角色编码（唯一）', type: 'string', example: 'admin')]
    public string $code = '';

    #[OA\Property(description: '角色描述', type: 'string', example: '系统管理员角色')]
    public string $description = '';

    #[OA\Property(description: '排序值', type: 'integer', example: 100)]
    public int $sort = 0;

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}
