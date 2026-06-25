<?php

namespace plugin\nanoadmin\app\validator;

use plugin\nanoadmin\app\model\Permission;

/**
 * 权限验证器
 *
 * 负责字段映射：
 * - 前端请求：resource_type / action_type
 * - Model/Service：resource / action
 */
class PermissionValidator extends ValidatorBase
{
    protected ?string $model = Permission::class;
    protected string $primaryKey = 'id';

    /**
     * 需要映射的字段
     * [前端字段名 => Model字段名]
     */
    private const FIELD_MAPPING = [
        'resource_type' => 'resource',
        'action_type' => 'action',
    ];

    public function rules(): array
    {
        return [
            'id' => 'require|integer|gt:0',
            'code' => 'require|string|min:2|max:100|regex:/^[a-zA-Z0-9_.-]+$/',
            'name' => 'require|string|min:2|max:100',
            'resource_type' => 'require|string|min:2|max:50|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            'action_type' => 'require|string|min:2|max:50|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            'description' => 'string|max:500',
            'status' => 'integer|in:0,1',
            'sort' => 'integer|egt:0',
            'ids' => 'require|array|min:1',
            'ids.*' => 'require|integer|gt:0',
            'keyword' => 'string|max:100',
            'page' => 'integer|gt:0',
            'limit' => 'integer|gt:0|max:100',
        ];
    }

    protected $messages = [
        'code.require' => '权限代码不能为空',
        'code.regex' => '权限代码只能包含字母、数字、下划线、点和连字符',
        'name.require' => '权限名称不能为空',
        'resource_type.require' => '资源类型不能为空',
        'resource_type.regex' => '资源类型必须以字母开头，只能包含字母、数字和下划线',
        'action_type.require' => '操作类型不能为空',
        'action_type.regex' => '操作类型必须以字母开头，只能包含字母、数字和下划线',
    ];

    protected function scenes(): array
    {
        return [
            'page' => ['page', 'limit', 'keyword', 'resource_type', 'action_type'],
            'show' => ['id'],
            'store' => ['code', 'name', 'resource_type', 'action_type', 'description', 'sort', 'status'],
            'update' => ['id', 'code', 'name', 'resource_type', 'action_type', 'description', 'sort', 'status'],
            'destroy' => ['id'],
            'batchDestroy' => ['ids'],
        ];
    }

    /**
     * 执行校验并映射字段
     *
     * @param array|null $data
     * @return array
     * @throws \plugin\nanoadmin\app\common\ApiException
     */
    public function check(?array $data = null): array
    {
        $validated = parent::check($data);
        return $this->mapFields($validated);
    }

    /**
     * 字段名映射
     *
     * 将前端字段名映射为 Model/Service 字段名
     */
    private function mapFields(array $data): array
    {
        foreach (self::FIELD_MAPPING as $from => $to) {
            if (isset($data[$from])) {
                $data[$to] = $data[$from];
                unset($data[$from]);
            }
        }
        return $data;
    }
}
