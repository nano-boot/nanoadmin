<?php

namespace plugin\nanoadmin\app\schema\dept;

use OpenApi\Attributes as OA;

/**
 * 部门请求体
 */
class DeptRequest
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

    public function __construct()
    {
    }
}
