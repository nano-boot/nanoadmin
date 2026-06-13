<?php

namespace plugin\nanoadmin\app\validator;

use think\Validate;

/**
 * 文件验证器
 *
 * @author TheAdmin Team
 * @since 1.0.0
 *
 */
class FileValidator extends ValidatorBase
{
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'ids' => 'require|array|min:1',
        'ids.*' => 'integer|gt:0',
        'page' => 'integer|min:1',
        'limit' => 'integer|min:1|max:100',
        'keyword' => 'string|max:100',
        'status' => 'integer|in:0,1',
        'file_type' => 'string|in:image,document,video,audio,archive,other',
        'storage_type' => 'string|in:local,cloud',
        'created_by' => 'integer|gt:0',
        'file' => 'require|max:102400', // 最大100MB
        'files' => 'array|min:1|max:10', // 最多10个文件
        'files.*' => 'file|max:102400',
        'bucket_name' => 'string|max:100',
        'original_name' => 'string|max:255',
        'file_name' => 'string|max:255',
        'file_path' => 'string|max:500',
        'file_size' => 'integer|gt:0',
        'file_ext' => 'string|max:20',
        'mime_type' => 'string|max:100',
        'file_hash' => 'string|max:128'
    ];

    protected $message = [
        'id.required' => 'ID不能为空',
        'id.integer' => 'ID必须是整数',
        'id.gt' => 'ID必须大于0',
        'ids.required' => 'ID数组不能为空',
        'ids.array' => 'ID必须是数组',
        'ids.min' => '至少选择一个文件',
        'ids.*.integer' => 'ID必须是整数',
        'ids.*.gt' => 'ID必须大于0',
        'page.integer' => '页码必须是整数',
        'page.min' => '页码必须大于0',
        'limit.integer' => '每页数量必须是整数',
        'limit.min' => '每页数量必须大于0',
        'limit.max' => '每页数量不能超过100',
        'keyword.string' => '关键词必须是字符串',
        'keyword.max' => '关键词长度不能超过100个字符',
        'status.integer' => '状态必须是整数',
        'status.in' => '状态值只能是0或1',
        'file_type.string' => '文件类型必须是字符串',
        'file_type.in' => '文件类型只能是image、document、video、audio或archive',
        'storage_type.string' => '存储类型必须是字符串',
        'storage_type.in' => '存储类型只能是local或cloud',
        'created_by.integer' => '创建者ID必须是整数',
        'created_by.gt' => '创建者ID必须大于0',
        'file.required' => '请选择要上传的文件',
        'file.file' => '上传的文件无效',
        'file.max' => '文件大小不能超过100MB',
        'files.array' => '文件必须是数组',
        'files.min' => '至少上传一个文件',
        'files.max' => '最多只能上传10个文件',
        'files.*.file' => '上传的文件无效',
        'files.*.max' => '单个文件大小不能超过100MB',
        'bucket_name.string' => '存储桶名称必须是字符串',
        'bucket_name.max' => '存储桶名称长度不能超过100个字符',
        'original_name.string' => '原始文件名必须是字符串',
        'original_name.max' => '原始文件名长度不能超过255个字符',
        'file_name.string' => '文件名必须是字符串',
        'file_name.max' => '文件名长度不能超过255个字符',
        'file_path.string' => '文件路径必须是字符串',
        'file_path.max' => '文件路径长度不能超过500个字符',
        'file_size.integer' => '文件大小必须是整数',
        'file_size.gt' => '文件大小必须大于0',
        'file_ext.string' => '文件扩展名必须是字符串',
        'file_ext.max' => '文件扩展名长度不能超过20个字符',
        'mime_type.string' => 'MIME类型必须是字符串',
        'mime_type.max' => 'MIME类型长度不能超过100个字符',
        'file_hash.string' => '文件哈希必须是字符串',
        'file_hash.max' => '文件哈希长度不能超过128个字符'
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'upload' => ['file', 'file_type'],
        'batch_upload' => ['files'],
        'download' => ['id'],
        'delete' => ['id'],
        'batch_delete' => ['ids'],
        'show' => ['id'],
        'list' => ['page', 'limit', 'keyword', 'status', 'file_type', 'storage_type', 'created_by'],
        'update' => ['id', 'original_name', 'file_name', 'file_path', 'status']
    ];

    /**
     * 验证文件上传数据
     */
    public function validateUploadData(array $data): array
    {
        return $this->validateData($data, 'upload');
    }

    /**
     * 验证批量上传数据
     */
    public function validateBatchUploadData(array $data): array
    {
        return $this->validateData($data, 'batch_upload');
    }

    /**
     * 验证列表参数
     */
    public function validateListParams(array $data): array
    {
        return $this->validateData($data, 'list');
    }

    /**
     * 验证ID参数
     */
    public function validateId($id): int
    {
        $data = $this->validateData(['id' => $id], 'show');
        return (int)$data['id'];
    }

    /**
     * 验证批量ID数据
     */
    public function validateBatchIds(array $data): array
    {
        return $this->validateData($data, 'batch_delete');
    }

    /**
     * 验证更新数据
     */
    public function validateUpdateData(array $data): array
    {
        return $this->validateData($data, 'update');
    }

    /**
     * 获取经过验证的请求数据
     *
     * @param string $scene 验证场景
     * @return array
     */
    public function validated(string $scene = null): array
    {
        $data = $this->all();
        return $this->validateData($data, $scene);
    }

    /**
     * 获取指定字段的验证数据
     *
     * @param array $fields 字段名数组
     * @return array
     */
    public function only(array $fields): array
    {
        $data = $this->all();
        return array_intersect_key($data, array_flip($fields));
    }
}
