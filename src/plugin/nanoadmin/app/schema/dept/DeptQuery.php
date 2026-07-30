<?php

namespace plugin\nanoadmin\app\schema\dept;

use OpenApi\Attributes as OA;

/**
 * 部门查询参数
 */
class DeptQuery
{
    #[OA\Parameter(parameter: 'page', name: 'page', description: '页码', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(parameter: 'limit', name: 'limit', description: '每页数量', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15))]
    #[OA\Parameter(parameter: 'keyword', name: 'keyword', description: '关键词搜索', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(parameter: 'status', name: 'status', description: '状态', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
    #[OA\Parameter(parameter: 'parent_id', name: 'parent_id', description: '父部门ID', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    public function __construct()
    {
    }
}
