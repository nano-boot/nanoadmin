<?php

namespace plugin\nanoadmin\app\schema\auth;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * Token 检查结果响应结构
 */
#[OA\Schema(title: 'Token 检查结果', description: 'Token 校验结果')]
class CheckResponse extends ResponseSchema
{
    #[OA\Property(description: '是否有效', type: 'boolean', example: true)]
    public bool $valid = false;

    #[OA\Property(description: '剩余有效秒数，-1 表示已过期或无效', type: 'integer', example: 3600)]
    public int $remaining_time = -1;

    #[OA\Property(description: '用户ID（无效时为 null）', type: 'integer', example: 1, nullable: true)]
    public ?int $user_id = null;

    #[OA\Property(description: '错误信息（仅当 valid=false 时存在）', type: 'string', example: 'Token 已过期', nullable: true)]
    public ?string $error = null;
}
