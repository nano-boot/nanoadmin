<?php

namespace plugin\nanoadmin\app\schema\file;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 文件请求结构
 *
 * 注意：文件上传不走 RequestBody，走 form-data，请使用自定义的 OpenAPI 配置。
 * 此处仅用于更新操作等需要 JSON body 的场景。
 */
#[OA\Schema(title: '文件请求', description: '文件创建/更新请求参数')]
class FileRequest extends RequestSchema
{
    #[OA\Property(description: 'ID（更新时必填）', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '原始文件名', type: 'string', example: 'report.pdf')]
    public string $original_name = '';

    #[OA\Property(description: '文件名', type: 'string', example: '65f1a2b3c4d5.pdf')]
    public string $file_name = '';

    #[OA\Property(description: '文件路径', type: 'string', example: 'up/2024/01/15/')]
    public string $file_path = '';

    #[OA\Property(description: '状态（0禁用 1正常）', type: 'integer', example: 1)]
    public int $status = 1;
}
