<?php

namespace plugin\nanoadmin\app\schema\dept;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 部门请求体
 *
 * 只做 OpenAPI 文档，校验统一走 DeptValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\dept\DeptValidator
 */
#[OA\Schema(title: '部门请求', description: '部门创建/更新请求参数')]
class DeptRequest extends RequestSchema
{
    #[OA\Property(description: '部门名称', example: '研发部')]
    public string $name;

    #[OA\Property(description: '部门编码', example: 'RD', nullable: true)]
    public ?string $code;

    #[OA\Property(description: '父部门ID', example: 1, nullable: true)]
    public ?int $parent_id;

    #[OA\Property(description: '联系电话', example: '13800138000', nullable: true)]
    public ?string $phone;

    #[OA\Property(description: '邮箱', example: 'rd@example.com', nullable: true)]
    public ?string $email;

    #[OA\Property(description: '排序', example: 100, nullable: true)]
    public ?int $sort;

    #[OA\Property(description: '状态', example: 1, nullable: true)]
    public ?int $status;
}
