<?php

namespace plugin\nanoadmin\app\schema\dict;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 字典数据创建/更新请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 DictDataValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\dict\DictDataValidator
 */
#[OA\Schema(title: '字典数据请求', description: '字典数据创建/更新请求参数')]
class DictDataRequest extends RequestSchema
{
    #[OA\Property(description: '字典数据ID（更新时必填）', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '字典类型ID', type: 'integer', example: 1)]
    public int $dict_type_id = 0;

    #[OA\Property(description: '字典标签', type: 'string', example: '男')]
    public string $label = '';

    #[OA\Property(description: '字典值', type: 'string', example: '1')]
    public string $value = '';

    #[OA\Property(description: '排序', type: 'integer', example: 0)]
    public int $sort = 0;

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}
