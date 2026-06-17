<?php

namespace plugin\nanoadmin\app\schema\auth;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 当前管理员信息响应结构
 *
 * - permissionScope：后端统一权限范围（allow_all / limited）
 * - permissionCodes：权限码数组
 * - buttons：框架兼容保留字段，值同 permissionCodes
 */
#[OA\Schema(title: '当前管理员信息', description: '当前登录管理员详细信息')]
class AuthInfoResponse extends ResponseSchema
{
    #[OA\Property(description: '管理员ID', type: 'integer', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '用户名', type: 'string', example: 'admin')]
    public string $username = '';

    #[OA\Property(description: '昵称', type: 'string', example: '超级管理员')]
    public string $nickname = '';

    #[OA\Property(description: '邮箱', type: 'string', example: 'admin@example.com')]
    public string $email = '';

    #[OA\Property(description: '手机号', type: 'string', example: '13800138000')]
    public string $phone = '';

    #[OA\Property(description: '头像URL', type: 'string', example: '')]
    public string $avatar = '';

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;

    #[OA\Property(description: '性别（0未知 1男 2女）', type: 'integer', example: 1)]
    public int $gender = 0;

    #[OA\Property(description: '最后登录时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $last_login_time = '';

    #[OA\Property(description: '创建时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $created_at = '';

    #[OA\Property(description: '角色编码列表', type: 'array', items: new OA\Items(type: 'string'), example: ['R_SUPER'])]
    public array $roles = [];

    #[OA\Property(
        description: '权限范围',
        type: 'object',
        properties: [
            new OA\Property(property: 'scope', description: '范围类型（allow_all 超管全放行 / limited 精确授权）', type: 'string', example: 'allow_all'),
            new OA\Property(property: 'codes', description: '权限码列表', type: 'array', items: new OA\Items(type: 'string')),
        ]
    )]
    public object $permissionScope;

    #[OA\Property(description: '权限码列表（permissionScope.codes 的快捷字段）', type: 'array', items: new OA\Items(type: 'string'))]
    public array $permissionCodes = [];

    #[OA\Property(description: '按钮权限码列表（兼容字段）', type: 'array', items: new OA\Items(type: 'string'))]
    public array $buttons = [];
}
