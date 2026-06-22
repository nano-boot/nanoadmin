<?php

namespace plugin\nanoadmin\app\schema\dict;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 字典类型创建/更新请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 DictTypeValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\dict\DictTypeValidator
 */
#[OA\Schema(title: '字典类型请求', description: '字典类型创建/更新请求参数')]
class DictTypeRequest extends RequestSchema
{
    #[OA\Property(description: '字典类型ID（更新时必填）', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '字典名称', type: 'string', example: '性别')]
    public string $name = '';

    #[OA\Property(description: '字典编码', type: 'string', example: 'gender')]
    public string $code = '';

    #[OA\Property(description: '字典描述', type: 'string', example: '用户性别字典')]
    public string $description = '';

    #[OA\Property(description: '排序', type: 'integer', example: 0)]
    public int $sort = 0;

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}