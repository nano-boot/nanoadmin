<?php

namespace plugin\nanoadmin\app\schema\log;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 操作日志查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 LogOperationValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\log\LogOperationValidator
 */
#[OA\Schema(title: '操作日志查询', description: '操作日志列表查询参数')]
class LogOperationQuery extends QuerySchema
{
    #[OA\Property(description: '页码', type: 'integer', example: 1)]
    public int $current = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $size = 20;

    #[OA\Property(description: '用户名（模糊查询）', type: 'string', example: 'admin')]
    public string $username = '';

    #[OA\Property(description: '操作模块', type: 'string', example: '管理员管理')]
    public string $module = '';

    #[OA\Property(description: '操作类型', type: 'string', example: '创建')]
    public string $action = '';

    #[OA\Property(description: '操作IP', type: 'string', example: '127.0.0.1')]
    public string $ip = '';

    #[OA\Property(description: '请求方法', type: 'string', example: 'POST')]
    public string $request_method = '';

    #[OA\Property(description: 'HTTP状态码', type: 'integer', example: 200)]
    public int $http_status = 0;

    #[OA\Property(description: '业务状态码', type: 'integer', example: 20000)]
    public int $response_code = 0;

    #[OA\Property(description: '关键词（用户名/描述/URL/响应消息）', type: 'string', example: 'admin')]
    public string $keyword = '';

    #[OA\Property(description: '开始时间', type: 'string', example: '2025-01-01 00:00:00')]
    public string $start_time = '';

    #[OA\Property(description: '结束时间', type: 'string', example: '2025-12-31 23:59:59')]
    public string $end_time = '';
}
