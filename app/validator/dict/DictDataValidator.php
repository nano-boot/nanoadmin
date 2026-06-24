<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\dict;

use plugin\nanoadmin\app\model\DictData;
use plugin\nanoadmin\app\validator\ValidatorBase;
use support\validation\Rule;

/**
 * 字典数据验证器
 *
 * 使用示例：
 * ```php
 * // 创建字典数据
 * $data = $validator->scene('store')->setPost()->check();
 * ```
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
class DictDataValidator extends ValidatorBase
{
    /**
     * 模型类（用于 unique/exists 规则自动解析表名）
     *
     * @var string|null
     */
    protected ?string $model = DictData::class;

    /**
     * 主键字段
     */
    protected string $primaryKey = 'id';

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
            'dict_type_id' => [
                'required',
                'integer',
                'gt:0',
            ],
            'label' => [
                'required',
                'string',
                'min:1',
                'max:100',
            ],
            'value' => [
                'required',
                'string',
                'min:1',
                'max:255',
            ],
            'sort' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
            'status' => [
                'nullable',
                'integer',
                Rule::in([0, 1]),
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
            'keyword' => [
                'nullable',
                'string',
                'max:100',
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

            'dict_type_id.required' => '字典类型ID不能为空',
            'dict_type_id.integer' => '字典类型ID必须是整数',
            'dict_type_id.gt' => '字典类型ID必须大于0',

            'label.required' => '字典标签不能为空',
            'label.string' => '字典标签必须是字符串',
            'label.min' => '字典标签长度至少1个字符',
            'label.max' => '字典标签长度不能超过100个字符',

            'value.required' => '字典值不能为空',
            'value.string' => '字典值必须是字符串',
            'value.min' => '字典值长度至少1个字符',
            'value.max' => '字典值长度不能超过255个字符',

            'sort.integer' => '排序必须是整数',
            'sort.min' => '排序必须大于等于0',
            'sort.max' => '排序必须小于等于9999',

            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1',

            'current.integer' => '页码必须是整数',
            'current.min' => '页码必须大于0',

            'size.integer' => '每页数量必须是整数',
            'size.min' => '每页数量必须大于0',
            'size.max' => '每页数量不能超过100',

            'keyword.string' => '关键词必须是字符串',
            'keyword.max' => '关键词长度不能超过100个字符',

            'ids.required' => 'ID数组不能为空',
            'ids.array' => 'ID必须是数组',
            'ids.min' => '至少选择一个项目',
            'ids.*.integer' => 'ID必须是整数',
            'ids.*.gt' => 'ID必须大于0',
        ];
    }

    /**
     * 场景定义
     */
    public function scenes(): array
    {
        return [
            'page' => [
                'current',
                'size',
                'dict_type_id',
                'keyword',
                'status',
            ],
            'store' => [
                'dict_type_id',
                'label',
                'value',
                'sort',
                'status',
            ],
            'update' => [
                'id',
                'dict_type_id',
                'label',
                'value',
                'sort',
                'status',
            ],
            'show' => ['id'],
            'destroy' => ['id'],
            'batchDestroy' => ['ids'],
        ];
    }
}
