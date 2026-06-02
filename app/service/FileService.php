<?php

namespace plugin\theadmin\app\service;

use Illuminate\Pagination\LengthAwarePaginator;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\File;

/**
 * 文件服务类
 */
class FileService extends BaseService
{
    /**
     * 构造函数
     * @param File $model 文件模型实例
     */
    public function __construct(File $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取记录不存在时的错误代码
     * @return Code
     */
    protected function getNotFoundCode(): Code
    {
        return Code::PARAMETER_ERROR;
    }

    /**
     * 获取记录不存在时的错误消息
     * @return string
     */
    protected function getNotFoundMessage(): string
    {
        return '文件不存在';
    }

    /**
     * 获取文件列表
     * @param array $params 查询参数
     *  - page: 页码
     *  - limit: 每页数量
     *  - keyword: 关键词（original_name 模糊搜）
     *  - status: 状态（0/1）
     *  - file_type: 文件类型（image/document/video/audio/archive）
     *  - storage_type: 存储类型（local/cloud）
     *  - created_by: 上传者ID
     * @return LengthAwarePaginator
     */
    public function getPage(array $params = []): LengthAwarePaginator
    {
        return parent::getPage($params);
    }

    /**
     * 上传文件
     * @param mixed $uploadedFile 上传的文件
     * @param array $params 其他参数
     *  - storage_type: 存储类型（local/cloud）
     *  - bucket_name: 存储桶名称
     *  - base_path: 自定义基础路径，默认 'd' (default)
     * @return File
     * @throws ApiException
     */
    public function uploadFile($uploadedFile, array $params = []): File
    {
        // 验证文件
        $this->validateUploadedFile($uploadedFile);

        // 生成文件信息
        $fileInfo = $this->generateFileInfo($uploadedFile, $params);

        // 检查文件是否已存在（通过哈希值）
        $existingFile = $this->model->where('file_hash', $fileInfo['file_hash'])
            ->where('deleted', false)
            ->first();

        if ($existingFile) {
            // 文件已存在，直接返回现有文件信息
            return $existingFile;
        }

        // 确定存储路径
        $storagePath = $this->getStoragePath();

        // 保存文件到本地
        $filePath = $this->saveFileToLocal($uploadedFile, $fileInfo['file_name'], $storagePath);

        // 更新文件路径（完整存储路径）
        $fileInfo['file_path'] = $filePath;

        // 创建文件记录
        $file = $this->model->create($fileInfo);

        if (!$file) {
            // 创建失败，删除已保存的文件
            $this->deleteLocalFile($filePath);
            throw new ApiException(Code::SYSTEM_ERROR, '文件上传失败');
        }

        return $file;
    }

    /**
     * 批量上传文件
     * @param array $uploadedFiles 上传的文件数组
     * @param array $params 其他参数
     * @return array
     * @throws ApiException
     */
    public function batchUploadFiles(array $uploadedFiles, array $params = []): array
    {
        $results = [];

        foreach ($uploadedFiles as $uploadedFile) {
            try {
                $file = $this->uploadFile($uploadedFile, $params);
                $results[] = $file;
            } catch (\Exception $e) {
                // 记录错误但不中断批量上传
                $results[] = [
                    'error' => true,
                    'message' => $e->getMessage(),
                    'original_name' => $uploadedFile->getClientOriginalName()
                ];
            }
        }

        return $results;
    }

    /**
     * 下载文件
     * @param int $id 文件ID
     * @return array
     * @throws ApiException
     */
    public function downloadFile(int $id): array
    {
        $file = $this->getById($id);

        if (!$file->exists()) {
            throw new ApiException(Code::PARAMETER_ERROR, '文件不存在或已被删除');
        }

        // 增加下载次数
        $file->incrementDownloadCount();

        return [
            'file_path' => $file->file_path,
            'file_name' => $file->original_name,
            'storage_type' => $file->storage_type,
            'file_url' => $file->file_url
        ];
    }

    /**
     * 删除文件
     * @param int $id 文件ID
     * @return bool
     * @throws ApiException
     */
    public function deleteFile(int $id): bool
    {
        /** @var File $file */
        $file = $this->getById($id);

        // 删除物理文件
        $file->deleteFile();

        // 删除数据库记录
        return parent::delete($id);
    }

    /**
     * 批量删除文件
     * @param array $ids 文件ID数组
     * @return int 删除数量
     * @throws ApiException
     */
    public function batchDeleteFiles(array $ids): int
    {
        // 规范化 IDs：确保是简单的一维数字数组
        $normalizedIds = [];
        foreach ($ids as $id) {
            if (is_array($id)) {
                // 如果元素是数组，递归扁平化
                foreach ($id as $subId) {
                    if (is_numeric($subId)) {
                        $normalizedIds[] = (int) $subId;
                    }
                }
            } elseif (is_numeric($id)) {
                $normalizedIds[] = (int) $id;
            }
        }
        $normalizedIds = array_unique($normalizedIds);

        if (empty($normalizedIds)) {
            throw new ApiException(Code::PARAMETER_ERROR, '请选择要删除的文件');
        }

        $files = $this->model->whereIn('id', $normalizedIds)->where('deleted', false)->get();

        foreach ($files as $file) {
            /** @var File $file */
            // 删除物理文件
            $file->deleteFile();
        }

        return parent::batchDelete($ids);
    }




    /**
     * 获取文件统计信息
     * @return array
     */
    public function getFileStats(): array
    {
        return $this->model->getFileStats();
    }

    /**
     * 验证上传文件
     * @param mixed $file
     * @throws ApiException
     */
    private function validateUploadedFile($file): void
    {
        // 检查文件大小（默认最大100MB）
        $maxSize = 100 * 1024 * 1024; // 100MB
        if ($file->getSize() > $maxSize) {
            throw new ApiException(Code::PARAMETER_ERROR, '文件大小不能超过100MB');
        }

        // 检查文件类型
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'video/mp4', 'video/avi', 'audio/mpeg', 'audio/wav',
            'application/zip', 'application/x-rar-compressed', 'text/plain'
        ];

        if (!in_array($file->getUploadMimeType(), $allowedMimes)) {
            throw new ApiException(Code::PARAMETER_ERROR, '不支持的文件类型');
        }
    }

    /**
     * 生成文件信息
     * @param mixed $file
     * @param array $params
     * @return array
     */
    private function generateFileInfo($file, array $params = []): array
    {
        $originalName = $file->getUploadName();
        $fileExt = strtolower($file->getUploadExtension());
        $fileSize = $file->getSize();
        $mimeType = $file->getUploadMimeType();

        // 生成唯一文件名（纯文件名）
        $fileName = uniqid() . '.' . $fileExt;

        // 计算文件哈希
        $fileHash = hash_file('sha256', $file->getRealPath());

        // 获取当前用户ID
        $currentUserId = $this->getCurrentUserId();

        // 确定文件类型（优先使用传入的参数，否则自动检测）
        $fileType = $params['file_type'] ?? $this->getFileTypeByExtension($fileExt);
        
        return [
            'original_name' => $originalName,
            'file_name' => $fileName,
            'file_path' => '',
            'file_size' => $fileSize,
            'file_ext' => $fileExt,
            'mime_type' => $mimeType,
            'file_hash' => $fileHash,
            'file_type' => $fileType,
            'storage_type' => $params['storage_type'] ?? 'local',
            'bucket_name' => $params['bucket_name'] ?? '',
            'created_by' => $currentUserId,
            'updated_by' => $currentUserId,
            'download_count' => 0,
            'status' => 1
        ];
    }

    /**
     * 保存文件到本地
     * @param mixed $file
     * @param string $fileName
     * @param string $customPath 自定义存储路径
     * @return string
     * @throws ApiException
     */
    private function saveFileToLocal($file, string $fileName, string $customPath = ''): string
    {
        // 使用自定义路径或默认路径
        $baseUploadPath = public_path();
        $dirPath = trim($customPath, '/');
        $uploadPath = $dirPath ? $baseUploadPath . '/' . $dirPath : $baseUploadPath;

        // 确保目录存在
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $fullPath = $uploadPath . '/' . $fileName;

        // 确保子目录存在
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 移动文件
        $file->move($fullPath);

        return $dirPath . '/' . $fileName;
    }

    /**
     * 删除本地文件
     * @param string $filePath
     * @return bool
     */
    private function deleteLocalFile(string $filePath): bool
    {
        $fullPath = public_path('uploads/' . $filePath);
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return true;
    }

    /**
     * 获取文件存储路径
     * @param string $basePath 基础路径，默认 'up' （uploads）
     * @return string
     */
    private function getStoragePath(string $basePath = 'up'): string
    {
        return $basePath . '/' . date('Y/m/d');
    }


    /**
     * 获取当前用户ID
     * @return int
     */
    /**
     * 根据文件扩展名获取文件类型
     */
    private function getFileTypeByExtension(string $ext): string
    {
        $ext = strtolower($ext);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'])) {
            return 'image';
        }

        if (in_array($ext, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'])) {
            return 'video';
        }

        if (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'])) {
            return 'document';
        }

        if (in_array($ext, ['mp3', 'wav', 'flac', 'aac', 'ogg'])) {
            return 'audio';
        }

        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return 'archive';
        }

        return 'other';
    }

    private function getCurrentUserId(): int
    {
        // 从请求上下文中获取当前登录用户ID
        $request = request();
        return $request->adminId ?? 0;
    }
}
