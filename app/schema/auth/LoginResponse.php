<?php

namespace plugin\nanoadmin\app\schema\auth;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 管理员登录响应结构
 *
 * data.user：当前登录管理员基础信息
 * data.token：JWT 访问令牌与刷新令牌
 */
#[OA\Schema(title: '管理员登录响应', description: '管理员登录成功响应结构')]
class LoginResponse extends ResponseSchema
{
    #[OA\Property(
        description: '管理员信息',
        type: 'object',
        properties: [
            new OA\Property(property: 'id', description: '管理员ID', type: 'integer', example: 1),
            new OA\Property(property: 'username', description: '用户名', type: 'string', example: 'admin'),
            new OA\Property(property: 'nickname', description: '昵称', type: 'string', example: '超级管理员'),
            new OA\Property(property: 'phone', description: '手机号', type: 'string', example: '13800138000'),
            new OA\Property(property: 'email', description: '邮箱', type: 'string', example: 'admin@example.com'),
            new OA\Property(property: 'avatar', description: '头像URL', type: 'string', example: ''),
            new OA\Property(property: 'status', description: '状态（0禁用 1启用）', type: 'integer', example: 1),
            new OA\Property(property: 'roles', description: '角色编码列表', type: 'array', items: new OA\Items(type: 'string'), example: ['R_SUPER']),
        ]
    )]
    public object $user;

    #[OA\Property(
        description: 'Token 信息',
        type: 'object',
        properties: [
            new OA\Property(property: 'access_token', description: '访问令牌', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
            new OA\Property(property: 'refresh_token', description: '刷新令牌', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
            new OA\Property(property: 'expires_in', description: '访问令牌过期时间（秒）', type: 'integer', example: 7200),
            new OA\Property(property: 'token_type', description: 'Token 类型', type: 'string', example: 'Bearer'),
        ]
    )]
    public object $token;
}
