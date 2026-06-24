<?php

namespace plugin\nanoadmin\app\schema\file;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 文件查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 FileValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\file\FileValidator
 */
#[OA\Schema(title: '文件查询', description: '文件列表查询参数')]
class FileQuery extends QuerySchema
{
    #[OA\Property(description: '页码', type: 'integer', example: 1)]
    public int $current = 1;

    #[OA\Property(description: '每页数量', type: 'integer', example: 20)]
    public int $size = 20;

    #[OA\Property(description: '关键词（文件名模糊搜索）', type: 'string', example: 'report')]
    public string $keyword = '';

    #[OA\Property(description: '状态（0禁用 1正常）', type: 'integer', example: 1)]
    public int $status = 1;

    #[OA\Property(description: '文件类型过滤', type: 'string', example: 'image', enum: ['image', 'document', 'video', 'audio', 'archive', 'other'])]
    public string $file_type_filter = '';

    #[OA\Property(description: '存储类型过滤', type: 'string', example: 'local', enum: ['local', 'cloud'])]
    public string $storage_type_filter = '';

    #[OA\Property(description: '上传者ID', type: 'integer', example: 1)]
    public int $created_by = 0;
}
