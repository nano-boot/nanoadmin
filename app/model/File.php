<?php

namespace plugin\theadmin\app\model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * 文件模型
 * @property string $original_name 原始文件名
 * @property string $file_name 存储文件名
 * @property string $file_path 文件存储路径
 * @property int $file_size 文件大小
 * @property string $file_ext 文件扩展名
 * @property string $mime_type MIME类型
 * @property string $file_hash 文件哈希值
 * @property string $storage_type 存储类型
 * @property string $bucket_name 存储桶名称
 * @property int $created_by 创建者ID
 * @property int $updated_by 更新者ID
 * @property int $download_count 下载次数
 * @property int $status 状态
 * @property int $id
 */
class File extends BaseModel
{
    /**
     * 初始化搜索字段配置
     */
    protected static function boot(): void
    {
        parent::boot();

        // 设置文件模型的搜索字段配置
        static::setSearchLikeFields(['original_name']);
        static::setSearchEqualFields(['file_ext', 'mime_type', 'storage_type', 'status', 'created_by']);
        static::setSearchKeywordFields(['original_name']);
        static::setSearchRangeFields(['created_at', 'file_size']);
    }

    /**
     * 表名
     * @var string
     */
    protected $table = 'th_sys_file';

    /**
     * 主键
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的属性
     * @var array
     */
    protected $fillable = [
        'original_name', 'file_name', 'file_path', 'file_size', 'file_ext',
        'mime_type', 'file_hash', 'storage_type', 'bucket_name',
        'created_by', 'updated_by', 'download_count', 'status'
    ];

    /**
     * 隐藏字段
     * @var array
     */
    protected $hidden = [];

    /**
     * 自动追加的字段
     * @var array
     */
    protected $appends = [];

    /**
     * 文件大小格式化访问器
     * @return string
     */
    public function getFileSizeHumanAttribute(): string
    {
        $size = $this->file_size;
        if ($size >= 1073741824) {
            return round($size / 1073741824, 2) . ' GB';
        } elseif ($size >= 1048576) {
            return round($size / 1048576, 2) . ' MB';
        } elseif ($size >= 1024) {
            return round($size / 1024, 2) . ' KB';
        } else {
            return $size . ' B';
        }
    }

    /**
     * 文件URL访问器
     * @return string
     */
    public function getFileUrlAttribute(): string
    {
        if ($this->storage_type === 'local') {
            return '/uploads/' . $this->file_path;
        } elseif ($this->storage_type === 'cloud') {
            // 云存储URL，需要根据具体云存储服务配置
            return $this->bucket_name . '/' . $this->file_path;
        }
        return '';
    }

    /**
     * 关联创建者
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'id');
    }

    /**
     * 关联更新者
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by', 'id');
    }

    /**
     * 自定义搜索处理
     * @param Builder $query
     * @param array $params
     * @return Builder
     */
    public function handleSearch(Builder $query, array $params): Builder
    {
        $query = parent::handleSearch($query, $params);

        // 按文件类型筛选
        if (Arr::get($params, 'file_type')) {
            $fileType = Arr::get($params, 'file_type');
            switch ($fileType) {
                case 'image':
                    $query->whereIn('file_ext', ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                    break;
                case 'document':
                    $query->whereIn('file_ext', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt']);
                    break;
                case 'video':
                    $query->whereIn('file_ext', ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']);
                    break;
                case 'audio':
                    $query->whereIn('file_ext', ['mp3', 'wav', 'flac', 'aac', 'ogg']);
                    break;
                case 'archive':
                    $query->whereIn('file_ext', ['zip', 'rar', '7z', 'tar', 'gz']);
                    break;
            }
        }

        return $query;
    }

    /**
     * 增加下载次数
     * @return bool
     */
    public function incrementDownloadCount(): bool
    {
        return $this->increment('download_count');
    }

    /**
     * 检查文件是否存在
     * @return bool
     */
    public function exists(): bool
    {
        if ($this->storage_type === 'local') {
            return file_exists(public_path('uploads/' . $this->file_path));
        }
        // 云存储检查逻辑需要根据具体云存储服务实现
        return true;
    }

    /**
     * 删除文件
     * @return bool
     */
    public function deleteFile(): bool
    {
        if ($this->storage_type === 'local') {
            $filePath = public_path('uploads/' . $this->file_path);
            if (file_exists($filePath)) {
                return unlink($filePath);
            }
        }
        // 云存储删除逻辑需要根据具体云存储服务实现
        return true;
    }

    /**
     * 获取文件统计信息
     * @return array
     */
    public static function getFileStats(): array
    {
        $stats = [
            'total_count' => self::where('deleted', false)->count(),
            'total_size' => self::where('deleted', false)->sum('file_size'),
            'image_count' => self::where('deleted', false)->whereIn('file_ext', ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])->count(),
            'document_count' => self::where('deleted', false)->whereIn('file_ext', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'])->count(),
            'video_count' => self::where('deleted', false)->whereIn('file_ext', ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'])->count(),
            'audio_count' => self::where('deleted', false)->whereIn('file_ext', ['mp3', 'wav', 'flac', 'aac', 'ogg'])->count(),
        ];

        return $stats;
    }
}
