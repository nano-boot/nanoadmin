<?php

namespace plugin\nanoadmin\app\validator;

/**
 * 管理员验证器
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 *
 */
class RoleValidator extends ValidatorBase
{
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'name' => 'require|string|min:2|max:50|unique:sys_role,name',
        'code' => 'require|string|min:2|max:50|regex:/^[a-zA-Z0-9_-]+$/|unique:sys_role,code',
        'description' => 'string|max:255',
        'sort' => 'integer|between:0,9999',
        'status' => 'integer|between:0,1',
    ];

    protected $message = [
        'name.require' => '角色名称不能为空',
        'code.require' => '角色编码不能为空',
        'code.regex' => '角色编码只能包含字母、数字、下划线和短横线',
        'description.max' => '角色描述长度不能超过255个字符',
        'sort.between' => '角色排序必须在0-9999之间',
        'status.between' => '角色状态必须在0-1之间',
    ];

    protected $scene = [
        'store'  =>  ['name','code','description','sort','status'],
        'update'  =>  ['id','name','description','sort','status'],
        'assign_roles'  =>  ['id','role_ids'],
        'delete'  =>  ['id'],
        'batch_delete'  =>  ['ids'],
        'show'  =>  ['id'],
        'list'  =>  ['page','limit','keyword','status'],
    ];
}