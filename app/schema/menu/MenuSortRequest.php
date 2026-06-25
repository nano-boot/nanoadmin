<?php

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 菜单批量排序请求结构
 */
#[OA\Schema(title: '菜单批量排序', description: '菜单批量排序请求参数')]
class MenuSortRequest extends RequestSchema
{
    #[OA\Property(
        description: '排序数据数组，每项包含 id、sort、parent_id',
        type: 'array',
        items: new OA\Items(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', description: '菜单ID', type: 'integer', example: 1),
                new OA\Property(property: 'sort', description: '排序值', type: 'integer', example: 100),
                new OA\Property(property: 'parent_id', description: '父菜单ID', type: 'integer', example: 0),
            ]
        )
    )]
    public array $sort_data = [];
}
