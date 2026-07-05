<?php

namespace plugin\nanoadmin\app\library\swagger;

use OpenApi\Attributes as OA;

/**
 * 通用分页响应
 */
#[OA\Schema(title: '分页响应', description: '所有分页接口的通用响应结构')]
class PagedResponse extends ResponseSchema
{
    #[OA\Property(description: '当前页', type: 'integer', example: 1)]
    public int $current = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $size = 20;

    #[OA\Property(description: '记录总数', type: 'integer', example: 100)]
    public int $total = 0;

    #[OA\Property(description: '数据列表', type: 'array', items: new OA\Items(type: 'object'))]
    public array $records = [];
}
