<?php

namespace plugin\nanoadmin\app\schema\file;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 文件上传响应结构
 */
#[OA\Schema(title: '文件上传响应', description: '文件上传响应结构')]
class FileUploadResponse extends ResponseSchema
{
    #[OA\Property(description: '文件ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '原始文件名', type: 'string', example: 'report.pdf')]
    public string $original_name = '';

    #[OA\Property(description: '文件访问URL', type: 'string', example: '/uploads/up/2024/01/15/65f1a2b3c4d5.pdf')]
    public string $file_url = '';

    #[OA\Property(description: '文件大小（字节）', type: 'integer', format: 'int64', example: 102400)]
    public int $file_size = 0;

    #[OA\Property(description: '文件扩展名', type: 'string', example: 'pdf')]
    public string $file_ext = '';
}
