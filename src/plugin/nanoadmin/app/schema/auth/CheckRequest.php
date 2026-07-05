<?php

namespace plugin\nanoadmin\app\schema\auth;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 检查 Token 请求结构
 */
#[OA\Schema(title: '检查 Token', description: '校验 Token 是否有效')]
class CheckRequest extends RequestSchema
{
    #[OA\Property(description: 'JWT Token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...')]
    public string $token = '';
}
