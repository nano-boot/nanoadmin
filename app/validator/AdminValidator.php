<?php

namespace plugin\theadmin\app\validator;


/**
 * 管理员验证器
 * 
 * @author TheAdmin Team
 * @since 1.0.0
 */
class AdminValidator extends BaseValidator
{
    /**
     * 创建管理员数据验证
     */
    public static function validateCreateData(array $data): array
    {
        $rules = [
            'username' => 'required|string|min:3|max:20|regex:/^[a-zA-Z0-9_]+$/|unique:th_sys_admin',
            'password' => 'required|string|min:6|max:20',
            'nickname' => 'required|string|min:2|max:50',
            'phone' => 'nullable|string|regex:/^1[3-9]\d{9}$/',
            'email' => 'nullable|email|max:100',
            'avatar' => 'nullable|string|max:255',
            'status' => 'integer|in:0,1',
            'gender' => 'integer|in:0,1,2'
        ];

        $messages = [
            'username.required' => '用户名不能为空',
            'username.min' => '用户名长度必须在3-20个字符之间',
            'username.max' => '用户名长度必须在3-20个字符之间',
            'username.regex' => '用户名只能包含字母、数字和下划线',
            'password.required' => '密码不能为空',
            'password.min' => '密码长度必须在6-20个字符之间',
            'password.max' => '密码长度必须在6-20个字符之间',
            'nickname.required' => '昵称不能为空',
            'nickname.min' => '昵称长度必须在2-50个字符之间',
            'nickname.max' => '昵称长度必须在2-50个字符之间',
            'phone.regex' => '手机号格式不正确',
            'email.email' => '邮箱格式不正确',
            'email.max' => '邮箱长度不能超过100个字符',
            'avatar.max' => '头像URL长度不能超过255个字符',
            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1',
            'gender.in' => '性别值只能是男、女或未知'
        ];

        return self::validate($data, $rules, $messages);
    }

    /**
     * 更新管理员数据验证
     */
    public static function validateUpdateData(array $data): array
    {
        $rules = [
            'username' => 'string|min:3|max:20|regex:/^[a-zA-Z0-9_]+$/',
            'password' => 'nullable|string|min:6|max:20',
            'nickname' => 'string|min:2|max:50',
            'phone' => 'nullable|string|regex:/^1[3-9]\d{9}$/',
            'email' => 'nullable|email|max:100',
            'avatar' => 'nullable|string|max:255',
            'status' => 'integer|in:0,1',
            'gender' => 'integer|in:0,1,2'
        ];

        $messages = [
            'username.min' => '用户名长度必须在3-20个字符之间',
            'username.max' => '用户名长度必须在3-20个字符之间',
            'username.regex' => '用户名只能包含字母、数字和下划线',
            'password.min' => '密码长度必须在6-20个字符之间',
            'password.max' => '密码长度必须在6-20个字符之间',
            'nickname.min' => '昵称长度必须在2-50个字符之间',
            'nickname.max' => '昵称长度必须在2-50个字符之间',
            'phone.regex' => '手机号格式不正确',
            'email.email' => '邮箱格式不正确',
            'email.max' => '邮箱长度不能超过100个字符',
            'avatar.max' => '头像URL长度不能超过255个字符',
            'status.integer' => '状态必须是整数',
            'status.in' => '状态值只能是0或1',
            'gender.in' => '性别值只能是男、女或未知'
        ];

        return self::validate($data, $rules, $messages);
    }

    /**
     * 角色分配数据验证
     */
    public static function validateRoleAssignData(array $data): array
    {
        $rules = [
            'role_ids' => 'required|array',
            'role_ids.*' => 'integer|min:1'
        ];

        $messages = [
            'role_ids.required' => '角色ID列表不能为空',
            'role_ids.array' => '角色ID必须是数组格式',
            'role_ids.*.integer' => '角色ID必须是整数',
            'role_ids.*.min' => '角色ID必须大于0'
        ];

        return self::validate($data, $rules, $messages);
    }

    /**
     * 批量操作ID验证
     */
    public static function validateBatchIds(array $data): array
    {
        $rules = [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|min:1'
        ];

        $messages = [
            'ids.required' => '请选择要操作的管理员',
            'ids.array' => 'ID列表必须是数组格式',
            'ids.min' => '至少选择一个管理员',
            'ids.*.integer' => '管理员ID必须是整数',
            'ids.*.min' => '管理员ID必须大于0'
        ];

        return self::validate($data, $rules, $messages);
    }
}