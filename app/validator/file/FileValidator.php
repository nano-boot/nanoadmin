<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\file;

use plugin\nanoadmin\app\validator\ValidatorBaseWebman;
use support\validation\Rule as IlluminateRule;

/**
 * 文件验证器
 *
 * 使用示例：
 * ```php
 * // 上传验证
 * $data = $validator->scene('upload')->setPost()->check();
 * ```
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
class FileValidator extends ValidatorBaseWebman
{
    /**
     * 验证规则
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'gt:0',
            ],
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'integer',
                'gt:0',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'keyword' => [
                'nullable',
                'string',
                'max:100',
            ],
            'status' => [
                'nullable',
                'integer',
                IlluminateRule::in([0, 1]),
            ],
            'file_type' => [
                'nullable',
                'string',
                IlluminateRule::in(['image', 'document', 'video', 'audio', 'archive', 'other']),
            ],
            'file_type_filter' => [
                'nullable',
                'string',
                IlluminateRule::in(['image', 'document', 'video', 'audio', 'archive', 'other']),
            ],
            'storage_type' => [
                'nullable',
                'string',
                IlluminateRule::in(['local', 'cloud']),
            ],
            'storage_type_filter' => [
                'nullable',
                'string',
                IlluminateRule::in(['local', 'cloud']),
            ],
            'created_by' => [
                'nullable',
                'integer',
                'gt:0',
            ],
            'file' => [
                'required',
                'file',
                'max:102400', // 最大100MB
            ],
            'files' => [
                'nullable',
                'array',
                'min:1',
                'max:10', // 最多10个文件
            ],
            'files.*' => [
                'file',
                'max:102400', // 单个文件最大100MB
            ],
            'bucket_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'original_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'file_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'file_path' => [
                'nullable',
                'string',
                'max:500',
            ],
            'file_size' => [
                'nullable',
                'integer',
                'gt:0',
            ],
            'file_ext' => [
                'nullable',
                'string',
                'max:20',
            ],
            'mime_type' => [
                'nullable',
                'string',
                'max:100',
            ],
            'file_hash' => [
                'nullable',
                'string',
                'max:128',
            ],
            'current' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'size' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    /**
     * 自定义消息
     */
    public function messages(): array
    {
        return [
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

            'file_type_filter.string' => '文件类型过滤必须是字符串',
            'file_type_filter.in' => '文件类型过滤值不正确',

            'storage_type.string' => '存储类型必须是字符串',
            'storage_type.in' => '存储类型只能是local或cloud',

            'storage_type_filter.string' => '存储类型过滤必须是字符串',
            'storage_type_filter.in' => '存储类型过滤值不正确',

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
            'file_hash.max' => '文件哈希长度不能超过128个字符',

            'current.integer' => '页码必须是整数',
            'current.min' => '页码必须大于0',

            'size.integer' => '每页数量必须是整数',
            'size.min' => '每页数量必须大于0',
            'size.max' => '每页数量不能超过100',
        ];
    }

    /**
     * 场景定义
     */
    public function scenes(): array
    {
        return [
            'upload' => ['file', 'file_type'],
            'batchUpload' => ['files'],
            'download' => ['id'],
            'delete' => ['id'],
            'batchDelete' => ['ids'],
            'show' => ['id'],
            'list' => ['page', 'limit', 'keyword', 'status', 'file_type_filter', 'storage_type_filter', 'created_by'],
            'index' => ['page', 'limit', 'keyword', 'status', 'file_type_filter', 'storage_type_filter', 'created_by'],
            'update' => ['id', 'original_name', 'file_name', 'file_path', 'status'],
        ];
    }
}
