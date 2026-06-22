<?php

namespace plugin\nanoadmin\app\schema\dict;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 字典类型查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 DictTypeValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\dict\DictTypeValidator
 */
#[OA\Schema(title: '字典类型查询', description: '字典类型列表查询参数')]
class DictTypeQuery extends QuerySchema
{
    #[OA\Property(description: '页码', type: 'integer', example: 1)]
    public int $current = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $size = 20;

    #[OA\Property(description: '关键词（名称/编码）', type: 'string', example: 'gender')]
    public string $keyword = '';

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}