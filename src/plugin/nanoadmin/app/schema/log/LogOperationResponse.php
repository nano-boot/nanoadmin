<?php

namespace plugin\nanoadmin\app\schema\log;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 操作日志响应结构
 */
#[OA\Schema(title: '操作日志', description: '操作日志响应结构')]
class LogOperationResponse extends ResponseSchema
{
    #[OA\Property(description: 'ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '管理员ID', type: 'integer', format: 'int64', example: 1)]
    public int $admin_id = 0;

    #[OA\Property(description: '管理员名称', type: 'string', example: 'admin')]
    public string $username = '';

    #[OA\Property(description: '操作模块', type: 'string', example: '管理员管理')]
    public string $module = '';

    #[OA\Property(description: '操作类型', type: 'string', example: '创建')]
    public string $action = '';

    #[OA\Property(description: '操作描述', type: 'string', example: '创建管理员')]
    public string $description = '';

    #[OA\Property(description: '请求方法', type: 'string', example: 'POST')]
    public string $request_method = '';

    #[OA\Property(description: '请求URL', type: 'string', example: '/sys/admin')]
    public string $request_url = '';

    #[OA\Property(description: '请求参数', type: 'string', example: '{"username":"admin"}')]
    public string $request_params = '';

    #[OA\Property(description: '业务状态码', type: 'integer', example: 20000)]
    public int $response_code = 0;

    #[OA\Property(description: '响应消息', type: 'string', example: '操作成功')]
    public string $response_msg = '';

    #[OA\Property(description: 'HTTP状态码', type: 'integer', example: 200)]
    public int $http_status = 0;

    #[OA\Property(description: '消耗时间（秒）', type: 'number', format: 'float', example: 0.123)]
    public float $cost_time = 0;

    #[OA\Property(description: '操作IP', type: 'string', example: '127.0.0.1')]
    public string $ip = '';

    #[OA\Property(description: '操作时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $created_at = '';
}
