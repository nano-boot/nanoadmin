<?php

namespace plugin\theadmin\app\validator;



/**
 * 管理员验证器
 *
 * @author TheAdmin Team
 * @since 1.0.0
 *
 */
class AdminValidator extends ValidatorBase
{
    protected function rules()
    {  
        $id = '';
        if($this->supportRequest->admin){
            $id = $this->supportRequest->admin->id;
        }
        return [
            'username' => 'require|string|min:3|max:20|regex:/^[a-zA-Z0-9_]+$/|unique:sys_admin,username',
            'password' => 'requireWithout:id|string|min:6|max:20',
            'old_password' => 'require|string|min:6|max:20',
            'confirm_password' => 'require|string|confirm:password',
            'nickname' => 'string|min:2|max:50',
            'phone' => "string|regex:/^1[3-9]\d{9}$/|unique:sys_admin,phone,$id",
            'email' => "email|max:100|unique:sys_admin,email,$id",
            'avatar' => 'string|max:255',
            'status' => 'integer|in:0,1',
            'gender' => 'integer|in:0,1,2',
            'role_ids' => 'array',
            'role_ids.*' => 'integer|gt:0',
            'ids' => 'require|array|min:1',
            'ids.*' => 'integer|gt:0',
            'id' => 'require|integer|gt:0',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'keyword' => 'string|max:100'
        ];
    }   

    protected $message = [
        'username.required' => '用户名不能为空',
        'username.min' => '用户名长度必须在3-20个字符之间',
        'username.max' => '用户名长度必须在3-20个字符之间',
        'username.regex' => '用户名只能包含字母、数字和下划线',
        'username.unique' => '用户名已存在',
        'password.required' => '密码不能为空',
        'password.min' => '密码长度必须在6-20个字符之间',
        'password.max' => '密码长度必须在6-20个字符之间',
        'nickname.required' => '昵称不能为空',
        'nickname.min' => '昵称长度必须在2-50个字符之间',
        'nickname.max' => '昵称长度必须在2-50个字符之间',
        'phone.regex' => '手机号格式不正确',
        'phone.unique' => '手机号已存在',
        'email.email' => '邮箱格式不正确',
        'email.max' => '邮箱长度不能超过100个字符',
        'email.unique' => '邮箱已存在',
        'avatar.max' => '头像URL长度不能超过255个字符',
        'status.integer' => '状态必须是整数',
        'status.in' => '状态值只能是0或1',
        'gender.in' => '性别值只能是男、女或未知',
        'role_ids.array' => '角色ID必须是数组',
        'role_ids.*.integer' => '角色ID必须是整数',
        'role_ids.*.gt' => '角色ID必须大于0',
        'ids.required' => 'ID数组不能为空',
        'ids.array' => 'ID必须是数组',
        'ids.min' => '至少选择一个项目',
        'ids.*.integer' => 'ID必须是整数',
        'ids.*.gt' => 'ID必须大于0',
        'id.required' => 'ID不能为空',
        'id.integer' => 'ID必须是整数',
        'id.gt' => 'ID必须大于0',
        'page.integer' => '页码必须是整数',
        'page.min' => '页码必须大于0',
        'limit.integer' => '每页数量必须是整数',
        'limit.min' => '每页数量必须大于0',
        'limit.max' => '每页数量不能超过100',
        'keyword.string' => '关键词必须是字符串',
        'keyword.max' => '关键词长度不能超过100个字符'
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'login' => ['username', 'password'],
        'create' => ['username', 'password', 'nickname', 'phone', 'email', 'avatar', 'status', 'gender'],
        'update' => ['id','username','password', 'nickname', 'phone', 'email', 'avatar', 'status', 'gender'],
        'assign_roles' => ['role_ids'],
        'batch_delete' => ['ids'],
        'show' => ['id'],
        'delete' => ['id'],
        'list' => ['page', 'limit', 'keyword', 'status'],
        'update_password' => ['id', 'password'],
        'updateProfile' => ['nickname', 'phone', 'email', 'avatar', 'gender'],
        'update_current_password' => ['old_password', 'password', 'confirm_password']
    ];

    /**
     * 登录场景的验证规则（重写部分规则）
     */
    protected function sceneLogin()
    {
        // 为登录场景移除唯一性验证
        return $this->remove('username', 'unique');
    }



    /**
     * 验证登录参数
     */
    public function validateLoginData(array $data): array
    {
        return $this->validateData($data, 'login');
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
     * 验证角色分配数据
     */
    public function validateRoleAssignData(array $data): array
    {
        return $this->validateData($data, 'assign_roles');
    }

    /**
     * 验证批量ID数据
     */
    public function validateBatchIds(array $data): array
    {
        return $this->validateData($data, 'batch_delete');
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

    /**
     * 验证更新数据（排除当前记录的唯一性检查）
     *
     * @param array $data 要验证的数据
     * @param int $excludeId 要排除的记录ID
     * @return array
     * @throws \Exception
     */
    public function validateUpdateData(array $data, int $excludeId): array
    {
        // 动态修改唯一性规则，排除当前记录
        $rules = $this->rule;

        if (isset($data['username'])) {
            $rules['username'] = str_replace(
                'unique:sys_admin,username',
                'unique:sys_admin,username,' . $excludeId,
                $rules['username']
            );
        }

        if (isset($data['phone'])) {
            $rules['phone'] = str_replace(
                'unique:sys_admin,phone',
                'unique:sys_admin,phone,' . $excludeId,
                $rules['phone']
            );
        }

        if (isset($data['email'])) {
            $rules['email'] = str_replace(
                'unique:sys_admin,email',
                'unique:sys_admin,email,' . $excludeId,
                $rules['email']
            );
        }

        // 临时设置规则并验证
        $originalRule = $this->rule;
        $this->rule = $rules;

        try {
            $validatedData = $this->validateData($data, 'update');
            $this->rule = $originalRule; // 恢复原始规则
            return $validatedData;
        } catch (\Exception $e) {
            $this->rule = $originalRule; // 确保恢复原始规则
            throw $e;
        }
    }

}