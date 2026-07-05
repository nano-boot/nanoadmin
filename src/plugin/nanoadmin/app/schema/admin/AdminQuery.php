<?php

namespace plugin\nanoadmin\app\schema\admin;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 管理员查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 AdminValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\AdminValidator
 */
#[OA\Schema(title: '管理员查询', description: '管理员列表查询参数')]
class AdminQuery extends QuerySchema
{
    #[OA\Property(description: '页码', type: 'integer', example: 1)]
    public int $current = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $size = 20;

    #[OA\Property(description: '关键词（用户名/昵称/手机/邮箱）', type: 'string', example: 'admin')]
    public string $keyword = '';

    #[OA\Property(description: '状态（0禁用 1启用）', type: 'integer', example: 1)]
    public int $status = 1;
}
