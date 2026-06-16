<?php

namespace plugin\nanoadmin\app\schema\query;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\swagger\QuerySchema;

/**
 * 登录日志查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 LogLoginValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\LogLoginValidator
 */
#[OA\Schema(title: '登录日志查询', description: '登录日志列表查询参数')]
class LogLoginQuery extends QuerySchema
{
    #[OA\Property(description: '页码', type: 'integer', example: 1)]
    public int $current = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $size = 20;

    #[OA\Property(description: '用户名（模糊查询）', type: 'string', example: 'admin')]
    public string $username = '';

    #[OA\Property(description: '登录状态（0失败 1成功）', type: 'integer', example: 1)]
    public int $status = 0;

    #[OA\Property(description: '开始时间', type: 'string', example: '2025-01-01 00:00:00')]
    public string $start_time = '';

    #[OA\Property(description: '结束时间', type: 'string', example: '2025-12-31 23:59:59')]
    public string $end_time = '';
}
