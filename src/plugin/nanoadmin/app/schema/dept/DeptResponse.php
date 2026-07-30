<?php

namespace plugin\nanoadmin\app\schema\dept;

use OpenApi\Attributes as OA;

/**
 * 部门响应体
 */
class DeptResponse
{
    #[OA\Property(description: '部门ID', example: 1)]
    public int $id;

    #[OA\Property(description: '父部门ID', example: 0)]
    public int $parent_id;

    #[OA\Property(description: '父节点路径', example: ',0,')]
    public string $path;

    #[OA\Property(description: '部门名称', example: '总公司')]
    public string $name;

    #[OA\Property(description: '部门编码', example: 'HQ', nullable: true)]
    public ?string $code;

    #[OA\Property(description: '联系电话', example: '13800138000', nullable: true)]
    public ?string $phone;

    #[OA\Property(description: '邮箱', example: 'hq@example.com', nullable: true)]
    public ?string $email;

    #[OA\Property(description: '排序', example: 100)]
    public int $sort;

    #[OA\Property(description: '状态: 0禁用 1启用', example: 1)]
    public int $status;

    #[OA\Property(description: '创建时间', example: '2024-01-01 00:00:00', nullable: true)]
    public ?string $created_at;

    #[OA\Property(description: '更新时间', example: '2024-01-01 00:00:00', nullable: true)]
    public ?string $updated_at;

    #[OA\Property(description: '子部门列表', nullable: true)]
    public array $children;

    public function __construct()
    {
    }
}

/**
 * 部门树形响应体
 */
class DeptTreeResponse
{
    #[OA\Property(description: '部门ID', example: 1)]
    public int $id;

    #[OA\Property(description: '父部门ID', example: 0)]
    public int $parent_id;

    #[OA\Property(description: '父节点路径', example: ',0,')]
    public string $path;

    #[OA\Property(description: '部门名称', example: '总公司')]
    public string $name;

    #[OA\Property(description: '部门编码', example: 'HQ', nullable: true)]
    public ?string $code;

    #[OA\Property(description: '联系电话', example: '13800138000', nullable: true)]
    public ?string $phone;

    #[OA\Property(description: '邮箱', example: 'hq@example.com', nullable: true)]
    public ?string $email;

    #[OA\Property(description: '排序', example: 100)]
    public int $sort;

    #[OA\Property(description: '状态: 0禁用 1启用', example: 1)]
    public int $status;

    #[OA\Property(description: '子部门列表', nullable: true)]
    public array $children;

    public function __construct()
    {
    }
}
