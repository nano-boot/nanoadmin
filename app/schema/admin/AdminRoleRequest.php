<?php

namespace plugin\nanoadmin\app\schema\admin;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 角色分配请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 AdminValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\AdminValidator
 */
#[OA\Schema(title: '管理员角色分配请求', description: '为管理员分配角色请求参数')]
class AdminRoleRequest extends RequestSchema
{
    #[OA\Property(description: '角色ID列表', type: 'array', items: new OA\Items(type: 'integer', format: 'int64'), example: [1, 2])]
    public array $role_ids = [];
}
