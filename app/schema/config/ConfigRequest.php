<?php

namespace plugin\nanoadmin\app\schema\config;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 配置创建/更新请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 ConfigValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\ConfigValidator
 */
#[OA\Schema(title: '配置请求', description: '配置创建/更新请求参数')]
class ConfigRequest extends RequestSchema
{
    #[OA\Property(description: '配置ID（更新时必填）', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '配置名称', type: 'string', example: '站点名称')]
    public string $name = '';

    #[OA\Property(description: '配置键名（唯一）', type: 'string', example: 'site_name')]
    public string $key = '';

    #[OA\Property(description: '配置值', type: 'string', example: 'Nano Admin')]
    public string $value = '';

    #[OA\Property(
        description: '配置类型（text/number/boolean/select/radio/checkbox/textarea/json）',
        type: 'string',
        example: 'text'
    )]
    public string $type = 'text';

    #[OA\Property(description: '选项配置（JSON格式）', type: 'string', example: '{}')]
    public string $options = '';

    #[OA\Property(description: '配置分组', type: 'string', example: 'basic')]
    public string $group = '';

    #[OA\Property(description: '配置描述', type: 'string', example: '网站的基本名称')]
    public string $description = '';

    #[OA\Property(description: '排序值', type: 'integer', example: 0)]
    public int $sort = 0;

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}
