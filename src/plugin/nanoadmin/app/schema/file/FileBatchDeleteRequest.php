<?php

namespace plugin\nanoadmin\app\schema\file;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 批量删除文件请求结构
 */
#[OA\Schema(title: '批量删除文件请求', description: '批量删除文件请求参数')]
class FileBatchDeleteRequest extends RequestSchema
{
    #[OA\Property(
        description: '文件ID数组',
        type: 'array',
        items: new OA\Items(type: 'integer', format: 'int64', example: 1)
    )]
    public array $ids = [];
}
