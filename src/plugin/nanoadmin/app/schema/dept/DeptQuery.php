<?php

namespace plugin\nanoadmin\app\schema\dept;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 部门树查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 DeptValidator::tree（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\dept\DeptValidator
 */
#[OA\Schema(title: '部门树查询', description: '部门树查询参数')]
class DeptQuery extends QuerySchema
{
    #[OA\Property(description: '父部门ID（0 表示顶级部门）', type: 'integer', example: 0)]
    public int $parent_id = 0;

    #[OA\Property(description: '关键词（部门名称/编码模糊搜索）', type: 'string', example: '技术')]
    public string $keyword = '';

    #[OA\Property(description: '部门名称（精确模糊搜索）', type: 'string', example: '研发部')]
    public string $name = '';

    #[OA\Property(description: '部门编码（精确模糊搜索）', type: 'string', example: 'DEV')]
    public string $code = '';

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;

    #[OA\Property(description: '是否只获取启用的部门', type: 'boolean', example: true)]
    public bool $only_enabled = true;
}
