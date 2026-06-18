<?php

namespace plugin\nanoadmin\app\schema\config;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 批量更新配置请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 ConfigValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\ConfigValidator
 */
#[OA\Schema(title: '批量更新配置', description: '批量更新配置值请求参数')]
class ConfigBatchUpdateRequest extends RequestSchema
{
    #[OA\Property(
        description: '配置项列表',
        type: 'array',
        items: new OA\Items(
            type: 'object',
            properties: [
                new OA\Property(property: 'key', description: '配置键名', type: 'string', example: 'site_name'),
                new OA\Property(property: 'value', description: '配置值', type: 'string', example: 'Nano Admin'),
            ]
        )
    )]
    public array $items = [];
}
