<?php

namespace plugin\nanoadmin\app\validator;

/**
 * 系统配置验证器
 */
class ConfigValidator extends ValidatorBase
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'name' => 'require|max:100',
        'key' => 'require|max:100|alphaDash',
        'value' => 'max:255',
        'type' => 'require|in:text,number,boolean,select,radio,checkbox,textarea,json',
        'options' => 'max:1000',
        'group' => 'require|max:50',
        'description' => 'max:500',
        'sort' => 'number|max:999999',
        'status' => 'in:0,1',
        'current' => 'integer|min:1',
        'size' => 'integer|min:1|max:200',
        'keyword' => 'string|max:100',
        'ids' => 'require|array|min:1',
        'ids.*' => 'integer|gt:0',
        'items' => 'require|array|min:1',
        'items.*.key' => 'require|max:100|alphaDash',
        'items.*.value' => 'max:255',
    ];

    /**
     * 错误信息
     * @var array
     */
    protected $message = [
        'id.require' => '配置ID不能为空',
        'id.integer' => '配置ID必须为整数',
        'id.gt' => '配置ID必须大于0',
        'name.require' => '请输入配置名称',
        'name.max' => '配置名称最多100个字符',
        'key.require' => '请输入配置键名',
        'key.max' => '配置键名最多100个字符',
        'key.alphaDash' => '配置键名只能包含字母、数字、下划线和短横线',
        'type.require' => '请选择配置类型',
        'type.in' => '配置类型不正确',
        'options.max' => '选项配置最多1000个字符',
        'group.require' => '请选择配置分组',
        'group.max' => '配置分组最多50个字符',
        'description.max' => '配置描述最多500个字符',
        'ids.require' => '请选择要删除的配置',
        'ids.array' => '配置ID列表格式错误',
        'ids.min' => '至少选择一个配置',
        'items.require' => '请提交配置项列表',
        'items.array' => '配置项必须为数组',
        'items.min' => '至少提交一个配置项',
        'items.*.key.require' => '配置项键名不能为空',
    ];

    /**
     * 场景定义
     * @var array
     */
    protected $scene = [
        'store'        => ['name', 'key', 'type', 'group', 'value', 'options', 'description', 'sort', 'status'],
        'update'       => ['id', 'name', 'key', 'type', 'group', 'value', 'options', 'description', 'sort', 'status'],
        'index'        => ['current', 'size', 'keyword', 'group', 'type', 'status'],
        'page'         => ['current', 'size', 'keyword', 'group', 'type', 'status'],
        'show'         => ['id'],
        'destroy'      => ['id'],
        'batch_destroy'=> ['ids'],
        'batch_update' => ['items'],
        'get_by_group' => ['group'],
    ];
}
