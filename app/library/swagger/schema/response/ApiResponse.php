<?php

namespace plugin\nanoadmin\app\library\swagger\schema\response;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 统一响应结构
 */
#[OA\Schema(title: '统一响应', description: '所有 API 接口的通用响应结构')]
class ApiResponse extends ResponseSchema
{
    #[OA\Property(description: '状态码', type: 'integer', example: 20000)]
    public int $code = 0;

    #[OA\Property(description: '提示信息', type: 'string', example: '操作成功')]
    public string $msg = '';

    #[OA\Property(description: '业务数据')]
    public mixed $data = null;
}