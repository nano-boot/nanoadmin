<?php

namespace plugin\nanoadmin\app\schema\config;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 配置查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 ConfigValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\ConfigValidator
 */
#[OA\Schema(title: '配置查询', description: '配置列表查询参数')]
class ConfigQuery extends QuerySchema
{
    #[OA\Property(description: '页码', type: 'integer', example: 1)]
    public int $current = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $size = 20;

    #[OA\Property(description: '关键词（配置名称/键名/描述）', type: 'string', example: 'site')]
    public string $keyword = '';

    #[OA\Property(description: '配置分组', type: 'string', example: 'basic')]
    public string $group = '';

    #[OA\Property(description: '配置类型', type: 'string', example: 'text')]
    public string $type = '';

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}
