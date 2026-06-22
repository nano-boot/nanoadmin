<?php

namespace plugin\nanoadmin\app\schema\dict;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 字典数据响应结构
 */
#[OA\Schema(title: '字典数据', description: '字典数据响应结构')]
class DictDataResponse extends ResponseSchema
{
    #[OA\Property(description: 'ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '字典类型ID', type: 'integer', example: 1)]
    public int $dict_type_id = 0;

    #[OA\Property(description: '字典标签', type: 'string', example: '男')]
    public string $label = '';

    #[OA\Property(description: '字典值', type: 'string', example: '1')]
    public string $value = '';

    #[OA\Property(description: '排序', type: 'integer', example: 0)]
    public int $sort = 0;

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;

    #[OA\Property(description: '创建时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $create_time = '';

    #[OA\Property(description: '更新时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $update_time = '';
}
