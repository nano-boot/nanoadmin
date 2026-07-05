<?php

namespace plugin\nanoadmin\app\schema\auth;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 刷新 Token 请求结构
 */
#[OA\Schema(title: '刷新 Token', description: '使用刷新令牌获取新的访问令牌')]
class RefreshRequest extends RequestSchema
{
    #[OA\Property(description: '刷新令牌', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...')]
    public string $refresh_token = '';
}
