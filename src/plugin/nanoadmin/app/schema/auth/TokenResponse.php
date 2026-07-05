<?php

namespace plugin\nanoadmin\app\schema\auth;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * Token 响应结构
 *
 * 用于 refresh 接口的响应 data
 */
#[OA\Schema(title: 'Token', description: 'JWT Token 信息')]
class TokenResponse extends ResponseSchema
{
    #[OA\Property(description: '访问令牌', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...')]
    public string $access_token = '';

    #[OA\Property(description: '刷新令牌', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...')]
    public string $refresh_token = '';

    #[OA\Property(description: '访问令牌过期时间（秒）', type: 'integer', example: 7200)]
    public int $expires_in = 0;

    #[OA\Property(description: 'Token 类型', type: 'string', example: 'Bearer')]
    public string $token_type = 'Bearer';
}
