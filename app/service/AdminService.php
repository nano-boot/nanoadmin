<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\Admin;

/**
 * 管理员服务类
 */
class AdminService
{
    /**
     * 管理员模型实例
     * @var Admin
     */
    private Admin $model;

    /**
     * 构造函数
     * @param Admin $model 管理员模型实例
     */
    public function __construct(Admin $model)
    {
        $this->model = $model;
    }

    /**
     * 获取管理员列表
     * @param array $params 查询参数
     *  - page: 页码
     *  - limit: 每页数量
     *  - keyword: 关键词（username/real_name/email 模糊搜）
     *  - status: 状态（0/1）
     *  - role_id: 角色ID筛选
     * @return array { list: array, pagination: array }
     */
    public function getAdminList(array $params = []): array
    {
        // 分页参数
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, (int)($params['limit'] ?? 15));

        // 查询参数
        $keyword = trim((string)($params['keyword'] ?? ''));
        $status = $params['status'] ?? '';
        $roleId = $params['role_id'] ?? null;

        $query = Admin::query()
            ->with(['roles'])
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('username', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%")
                        ->orWhere('nickname', 'like', "%{$keyword}%");
                });
            })
            ->when($status !== '', function ($q) use ($status) {
                $q->where('status', (int)$status);
            })
            ->when(!empty($roleId), function ($q) use ($roleId) {
                $q->whereHas('roles', function ($sub) use ($roleId) {
                    $sub->where('id', $roleId);
                });
            });

        $paginator = $query->paginate($limit, ['*'], 'page', $page);
    
        $list = $paginator->getCollection()->map(function ($admin) {
            return $this->formatAdminRow($admin);
        })->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'size' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ]
        ];
    }

    /**
     * 将管理员模型格式化为数组行
     * @param Admin $admin
     * @return array
     */
    private function formatAdminRow($admin): array
    {
        return [
            'id' => $admin->id,
            'username' => $admin->username,
            'gender' => $admin->gender,
            'nickname' => $admin->nickname,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'avatar' => $admin->avatar,
            'status' => $admin->status,
            'roles' => $admin->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code
                ];
            })->toArray(),
            'last_login_time' => $admin->last_login_time,
            'last_login_ip' => $admin->last_login_ip,
            'created_at' => $admin->created_at,
            'updated_at' => $admin->updated_at
        ];
    }

    /**
     * 根据ID获取管理员详情
     * @param int $id 管理员ID
     * @return Admin
     * @throws ApiException
     */
    public function getAdminById(int $id): Admin
    {
        $adminModel = $this->model;
        $admin = $adminModel->with('roles')->find($id);
        
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        return $admin;
    }

    /**
     * 创建管理员
     * @param array $data 管理员数据
     * @return Admin
     * @throws ApiException
     */
    public function createAdmin(array $data): Admin
    {
        $adminModel = $this->model;
        
        // 检查用户名是否已存在
        if ($adminModel->where('username', $data['username'])->first()) {
            throw new ApiException(Code::DUPLICATE_NAME, '用户名已存在');
        }
        
        // 检查手机号是否已存在（如果提供了手机号）
        if (!empty($data['phone'])) {
            if ($adminModel->where('phone', $data['phone'])->first()) {
                throw new ApiException(Code::DUPLICATE_NAME, '手机号已存在');
            }
        }
        
        // 检查邮箱是否已存在（如果提供了邮箱）
        if (!empty($data['email'])) {
            if ($adminModel->where('email', $data['email'])->first()) {
                throw new ApiException(Code::DUPLICATE_NAME, '邮箱已存在');
            }
        }
        
        // 设置默认值
        $data['status'] = $data['status'] ?? true;
        $data['deleted'] = false;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // 创建管理员
        $admin = $adminModel->createAdmin($data);
        
        if (!$admin) {
            throw new ApiException(Code::SYSTEM_ERROR, '创建管理员失败');
        }
        
        return $admin;
    }

    /**
     * 更新管理员
     * @param int $id 管理员ID
     * @param array $data 更新数据
     * @return Admin
     * @throws ApiException
     */
    public function updateAdmin(int $id, array $data): Admin
    {
        $admin = $this->model->find($id);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        $admin->update($data);
        return $admin->fresh();
    }

    /**
     * 删除管理员（软删除）
     * @param int $id 管理员ID
     * @return bool
     * @throws ApiException
     */
    public function deleteAdmin(int $id): bool
    {
        $adminModel = $this->model;
        
        // 检查管理员是否存在
        $admin = $adminModel->find($id);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 软删除
        $result = $adminModel->destroy($id);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '删除管理员失败');
        }
        
        return true;
    }

    /**
     * 启用/禁用管理员
     * @param int $id 管理员ID
     * @param bool $status 状态
     * @return bool
     * @throws ApiException
     */
    public function toggleAdminStatus(int $id, bool $status): bool
    {
        $adminModel = $this->model;
        
        // 检查管理员是否存在
        $admin = $adminModel->find($id);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 更新状态
        $result = $adminModel->where('id', $id)->update([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新管理员状态失败');
        }
        
        return true;
    }

    /**
     * 为管理员分配角色
     * @param int $adminId 管理员ID
     * @param array $roleIds 角色ID数组
     * @return bool
     * @throws ApiException
     */
    public function assignRoles(int $adminId, array $roleIds): bool
    {
        $adminModel = $this->model;
        
        // 检查管理员是否存在
        $admin = $adminModel->find($adminId);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 验证角色是否存在
        if (!empty($roleIds)) {
            $roleModel = ModelFactory::role();
            $existingRoles = $roleModel->whereIn('id', $roleIds)->where('status', true)->column('id');
            
            $invalidRoleIds = array_diff($roleIds, $existingRoles);
            if (!empty($invalidRoleIds)) {
                throw new ApiException(Code::ROLE_NOT_FOUND, '角色不存在: ' . implode(',', $invalidRoleIds));
            }
        }
        
        // 分配角色
        $result = $admin->assignRoles($roleIds);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '分配角色失败');
        }
        
        return true;
    }

    /**
     * 获取管理员权限列表
     * @param int $adminId 管理员ID
     * @return array
     * @throws ApiException
     */
    public function getAdminPermissions(int $adminId): array
    {
        $adminModel = $this->model;
        
        // 检查管理员是否存在
        $admin = $adminModel->find($adminId);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        return $admin->getPermissions();
    }

    /**
     * 获取管理员角色列表（用于控制器展示）
     * @param int $adminId
     * @return array
     * @throws ApiException
     */
    public function getAdminRoles(int $adminId): array
    {
        $adminModel = $this->model;
        $admin = $adminModel->with('roles')->find($adminId);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }

        return $admin->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
            ];
        })->toArray();
    }

    /**
     * 获取管理员菜单列表
     * @param int $adminId 管理员ID
     * @return array
     * @throws ApiException
     */
    public function getAdminMenus(int $adminId): array
    {
        $adminModel = $this->model;
        
        // 检查管理员是否存在
        $admin = $adminModel->find($adminId);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        return $admin->getMenus();
    }

    /**
     * 检查管理员权限
     * @param int $adminId 管理员ID
     * @param string $permission 权限代码
     * @return bool
     */
    public function checkAdminPermission(int $adminId, string $permission): bool
    {
        try {
            $adminModel = $this->model;
            $admin = $adminModel->find($adminId);
            
            if (!$admin) {
                return false;
            }
            
            return $admin->hasPermission($permission);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查管理员角色
     * @param int $adminId 管理员ID
     * @param string $roleCode 角色代码
     * @return bool
     */
    public function checkAdminRole(int $adminId, string $roleCode): bool
    {
        try {
            $adminModel = $this->model;
            $admin = $adminModel->where('deleted', false)->find($adminId);
            
            if (!$admin) {
                return false;
            }
            
            return $admin->hasRole($roleCode);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 修改管理员密码
     * @param int $adminId 管理员ID
     * @param string $newPassword 新密码
     * @param string $oldPassword 旧密码（可选，用于验证）
     * @return bool
     * @throws ApiException
     */
    public function changePassword(int $adminId, string $newPassword, string $oldPassword = ''): bool
    {
        $adminModel = $this->model;
        
        // 检查管理员是否存在
        $admin = $adminModel->find($adminId);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 如果提供了旧密码，需要验证
        if (!empty($oldPassword)) {
            if (!$admin->verifyPassword($oldPassword)) {
                throw new ApiException(Code::PASSWORD_ERROR, '原密码错误');
            }
        }
        
        // 密码强度验证
        if (strlen($newPassword) < 6) {
            throw new ApiException(Code::PARAMETER_ERROR, '密码长度不能少于6位');
        }
        
        // 更新密码
        $result = $adminModel->where('id', $adminId)->update([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '修改密码失败');
        }
        
        return true;
    }


    /**
     * 批量删除管理员
     * @param array $ids 管理员ID数组
     * @return int 删除数量
     * @throws ApiException
     */
    public function batchDeleteAdmins(array $ids): int
    {
        if (empty($ids)) {
            throw new ApiException(Code::PARAMETER_ERROR, '请选择要删除的管理员');
        }
        
        $adminModel = $this->model;
        
        // 检查管理员是否存在
        $existingAdmins = $adminModel->whereIn('id', $ids)->column('id');
        $invalidIds = array_diff($ids, $existingAdmins);
        
        if (!empty($invalidIds)) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在: ' . implode(',', $invalidIds));
        }
        
        // 批量软删除
        $result = $adminModel->destroy($ids);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '批量删除管理员失败');
        }
        
        return $result;
    }

    /**
     * 更新管理员状态
     * @param int $id 管理员ID
     * @param int $status 状态值
     * @return bool
     * @throws ApiException
     */
    public function updateAdminStatus(int $id, int $status): bool
    {
        $adminModel = $this->model;
        
        // 检查管理员是否存在
        $admin = $adminModel->find($id);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 更新状态
        $result = $adminModel->where('id', $id)->update(['status' => $status]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '更新管理员状态失败');
        }
        
        return true;
    }

    /**
     * 重置管理员密码
     * @param int $id 管理员ID
     * @param string $password 新密码
     * @return bool
     * @throws ApiException
     */
    public function resetAdminPassword(int $id, string $password): bool
    {
        $adminModel = $this->model;
        
        // 检查管理员是否存在
        $admin = $adminModel->find($id);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 加密密码
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // 更新密码
        $result = $adminModel->where('id', $id)->update(['password' => $hashedPassword]);
        
        if ($result === false) {
            throw new ApiException(Code::SYSTEM_ERROR, '重置密码失败');
        }
        
        return true;
    }
}