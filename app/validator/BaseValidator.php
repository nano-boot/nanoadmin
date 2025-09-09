<?php

namespace plugin\theadmin\app\validator;

use plugin\theadmin\app\common\ApiException;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use plugin\theadmin\app\common\Code;

/**
 * 基础验证器
 * 
 * @author TheAdmin Team
 * @since 1.0.0
 */
abstract class BaseValidator
{
    /**
     * 执行验证
     * 
     * @param array $data 要验证的数据
     * @param array $rules 验证规则
     * @param array $messages 自定义错误消息
     * @return array 验证后的数据
     * @throws ApiException
     */
    protected static function validate(array $data, array $rules, array $messages = []): array
    {
        // 创建验证器工厂
        $loader = new ArrayLoader();
        $translator = new Translator($loader, 'zh');
        $factory = new ValidationFactory($translator);

        // 创建验证器实例
        $validator = $factory->make($data, $rules, $messages);

        // 验证失败时抛出异常
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            throw new ApiException(Code::PARAMETER_ERROR, implode('; ', $errors));
        }

        // 返回验证后的数据（只包含规则中定义的字段）
        return array_intersect_key($data, $rules);
    }

    /**
     * 列表查询参数验证
     *
     * @param array $params 查询参数
     * @return array 验证后的参数
     * @throws ApiException
     */
    public static function validateListParams(array $params): array
    {
        $rules = [
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'keyword' => 'string|max:100',
            'status' => 'integer|in:0,1',
            'sort_field' => 'string|max:50',
            'sort_order' => 'string|in:asc,desc'
        ];

        $messages = [
            'page.integer' => '页码必须是整数',
            'page.min' => '页码必须大于0',
            'limit.integer' => '每页数量必须是整数',
            'limit.min' => '每页数量必须大于0',
            'limit.max' => '每页数量不能超过100',
            'keyword.string' => '关键词必须是字符串',
            'keyword.max' => '关键词长度不能超过100个字符',
            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1',
            'sort_field.string' => '排序字段必须是字符串',
            'sort_field.max' => '排序字段长度不能超过50个字符',
            'sort_order.string' => '排序方向必须是字符串',
            'sort_order.in' => '排序方向只能是asc或desc'
        ];

        return self::validate($params, $rules, $messages);
    }

    /**
     * ID参数验证
     * 
     * @param mixed $id ID值
     * @return int 验证后的ID
     * @throws ApiException
     */
    public static function validateId($id): int
    {
        $data = ['id' => $id];
        $rules = ['id' => 'required|integer|min:1'];
        $messages = [
            'id.required' => 'ID不能为空',
            'id.integer' => 'ID必须是整数',
            'id.min' => 'ID必须大于0'
        ];

        $validated = self::validate($data, $rules, $messages);
        return (int) $validated['id'];
    }

    /**
     * 状态参数验证
     * 
     * @param mixed $status 状态值
     * @return int 验证后的状态
     * @throws ApiException
     */
    public static function validateStatus($status): int
    {
        $data = ['status' => $status];
        $rules = ['status' => 'required|integer|in:0,1'];
        $messages = [
            'status.required' => '状态不能为空',
            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1'
        ];

        $validated = self::validate($data, $rules, $messages);
        return (int) $validated['status'];
    }
}
