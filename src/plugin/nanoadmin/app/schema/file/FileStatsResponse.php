<?php

namespace plugin\nanoadmin\app\schema\file;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 文件统计响应结构
 */
#[OA\Schema(title: '文件统计', description: '文件统计响应结构')]
class FileStatsResponse extends ResponseSchema
{
    #[OA\Property(description: '总文件数', type: 'integer', example: 100)]
    public int $total_count = 0;

    #[OA\Property(description: '总存储大小（字节）', type: 'integer', format: 'int64', example: 104857600)]
    public int $total_size = 0;

    #[OA\Property(description: '今日上传数', type: 'integer', example: 5)]
    public int $today_count = 0;

    #[OA\Property(description: '今日上传大小（字节）', type: 'integer', format: 'int64', example: 5242880)]
    public int $today_size = 0;

    #[OA\Property(description: '图片数量', type: 'integer', example: 30)]
    public int $image_count = 0;

    #[OA\Property(description: '文档数量', type: 'integer', example: 25)]
    public int $document_count = 0;

    #[OA\Property(description: '视频数量', type: 'integer', example: 10)]
    public int $video_count = 0;

    #[OA\Property(description: '音频数量', type: 'integer', example: 15)]
    public int $audio_count = 0;

    #[OA\Property(description: '压缩包数量', type: 'integer', example: 5)]
    public int $archive_count = 0;

    #[OA\Property(description: '其他文件数量', type: 'integer', example: 15)]
    public int $other_count = 0;
}
