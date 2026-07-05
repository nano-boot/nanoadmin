<?php

namespace plugin\nanoadmin\app\schema\dict;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 字典类型响应结构
 */
#[OA\Schema(title: '字典类型', description: '字典类型响应结构')]
class DictTypeResponse extends ResponseSchema
{
    #[OA\Property(description: 'ID', type: 'integer', format: 'int64', example: 1)]
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

    #[OA\Property(description: '创建时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $create_time = '';

    #[OA\Property(description: '更新时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $update_time = '';
}