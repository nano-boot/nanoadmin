<?php

namespace plugin\nanoadmin\app\schema\file;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 文件响应结构
 */
#[OA\Schema(title: '文件', description: '文件响应结构')]
class FileResponse extends ResponseSchema
{
    #[OA\Property(description: 'ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '原始文件名', type: 'string', example: 'report.pdf')]
    public string $original_name = '';

    #[OA\Property(description: '存储文件名', type: 'string', example: '65f1a2b3c4d5.pdf')]
    public string $file_name = '';

    #[OA\Property(description: '文件路径', type: 'string', example: 'up/2024/01/15/65f1a2b3c4d5.pdf')]
    public string $file_path = '';

    #[OA\Property(description: '文件大小（字节）', type: 'integer', format: 'int64', example: 102400)]
    public int $file_size = 0;

    #[OA\Property(description: '文件扩展名', type: 'string', example: 'pdf')]
    public string $file_ext = '';

    #[OA\Property(description: 'MIME类型', type: 'string', example: 'application/pdf')]
    public string $mime_type = '';

    #[OA\Property(description: '文件哈希', type: 'string', example: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855')]
    public string $file_hash = '';

    #[OA\Property(description: '文件类型（image/document/video/audio/archive/other）', type: 'string', example: 'document')]
    public string $file_type = '';

    #[OA\Property(description: '存储类型（local/cloud）', type: 'string', example: 'local')]
    public string $storage_type = '';

    #[OA\Property(description: '存储桶名称', type: 'string', example: '')]
    public string $bucket_name = '';

    #[OA\Property(description: '文件访问URL', type: 'string', example: '/uploads/up/2024/01/15/65f1a2b3c4d5.pdf')]
    public string $file_url = '';

    #[OA\Property(description: '下载次数', type: 'integer', example: 0)]
    public int $download_count = 0;

    #[OA\Property(description: '状态（0禁用 1正常）', type: 'integer', example: 1)]
    public int $status = 1;

    #[OA\Property(description: '上传者ID', type: 'integer', example: 1)]
    public int $created_by = 0;

    #[OA\Property(description: '创建时间', type: 'string', example: '2024-01-15 10:30:00')]
    public string $create_time = '';

    #[OA\Property(description: '更新时间', type: 'string', example: '2024-01-15 10:30:00')]
    public string $update_time = '';
}
