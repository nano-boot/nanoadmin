<?php

namespace plugin\nanoadmin\app\schema\config;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 按分组获取的配置项响应结构（用于表单展示）
 */
#[OA\Schema(title: '配置项', description: '按分组返回的配置项结构')]
class ConfigItemResponse extends ResponseSchema
{
    #[OA\Property(description: '配置ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '配置键名', type: 'string', example: 'site_name')]
    public string $key = '';

    #[OA\Property(description: '配置名称', type: 'string', example: '站点名称')]
    public string $name = '';

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

    #[OA\Property(description: '配置描述', type: 'string', example: '网站的基本名称')]
    public string $description = '';

    #[OA\Property(description: '排序值', type: 'integer', example: 0)]
    public int $sort = 0;
}
