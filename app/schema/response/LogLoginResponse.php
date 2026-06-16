<?php

namespace plugin\nanoadmin\app\schema\response;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\swagger\ResponseSchema;

/**
 * 登录日志响应结构
 */
#[OA\Schema(title: '登录日志', description: '登录日志响应结构')]
class LogLoginResponse extends ResponseSchema
{
    #[OA\Property(description: 'ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '管理员ID', type: 'integer', format: 'int64', example: 1)]
    public int $admin_id = 0;

    #[OA\Property(description: '用户名', type: 'string', example: 'admin')]
    public string $username = '';

    #[OA\Property(description: '登录IP', type: 'string', example: '127.0.0.1')]
    public string $ip = '';

    #[OA\Property(description: '登录地点', type: 'string', example: '本地')]
    public string $location = '';

    #[OA\Property(description: '登录状态（0失败 1成功）', type: 'integer', example: 1)]
    public int $status = 0;

    #[OA\Property(description: '登录信息', type: 'string', example: '登录成功')]
    public string $login_info = '';

    #[OA\Property(description: '登录时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $login_time = '';
}
