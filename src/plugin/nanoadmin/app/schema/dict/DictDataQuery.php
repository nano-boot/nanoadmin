<?php

namespace plugin\nanoadmin\app\schema\dict;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 字典数据查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 DictDataValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\dict\DictDataValidator
 */
#[OA\Schema(title: '字典数据查询', description: '字典数据列表查询参数')]
class DictDataQuery extends QuerySchema
{
    #[OA\Property(description: '页码', type: 'integer', example: 1)]
    public int $current = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $size = 20;

    #[OA\Property(description: '字典类型ID', type: 'integer', example: 1)]
    public int $dict_type_id = 0;

    #[OA\Property(description: '关键词（标签/值）', type: 'string', example: '男')]
    public string $keyword = '';

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}
