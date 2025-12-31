<?php

namespace plugin\theadmin\app\service;

use Illuminate\Pagination\LengthAwarePaginator;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\Admin;

/**
 * 管理员服务类
 */
class AdminService extends BaseService
{
    /**
     * 构造函数
     * @param Admin $model 管理员模型实例
     */
    public function __construct(Admin $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取记录不存在时的错误代码
     * @return Code
     */
    protected function getNotFoundCode(): Code
    {
        return Code::ADMIN_NOT_FOUND;
    }

    /**
     * 获取记录不存在时的错误消息
     * @return string
     */
    protected function getNotFoundMessage(): string
    {
        return '管理员不存在';
    }

    /**
     * 获取管理员列表
     * @param array $params 查询参数
     *  - page: 页码
     *  - limit: 每页数量
     *  - keyword: 关键词（username/real_name/email 模糊搜）
     *  - status: 状态（0/1）
     *  - role_id: 角色ID筛选
     *  - 其他参数: 根据模型的 handleSearch 方法处理
     * @return LengthAwarePaginator
     */
    public function getPage(array $params = []): LengthAwarePaginator
    {
        // 调用父类的分页查询（已包含 adminRoles 关联加载）
        return parent::getPage($params);
    }


    /**
     * 根据ID获取管理员详情
     * @param int $id 管理员ID
     * @return Admin
     * @throws ApiException
     */
    public function getAdminById(int $id): Admin
    {
        return $this->model->with('roles')->find($id) ?? throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
    }

    /**
     * 创建管理员
     * @param array $data 管理员数据
     * @return Admin
     * @throws ApiException
     */
    public function createAdmin(array $data): Admin
    {
        // 检查用户名是否已存在
        if ($this->model->where('username', $data['username'])->first()) {
            throw new ApiException(Code::DUPLICATE_NAME, '用户名已存在');
        }

        // 检查手机号是否已存在（如果提供了手机号）
        if (!empty($data['phone'])) {
            if ($this->model->where('phone', $data['phone'])->first()) {
                throw new ApiException(Code::DUPLICATE_NAME, '手机号已存在');
            }
        }

        // 检查邮箱是否已存在（如果提供了邮箱）
        if (!empty($data['email'])) {
            if ($this->model->where('email', $data['email'])->first()) {
                throw new ApiException(Code::DUPLICATE_NAME, '邮箱已存在');
            }
        }

        // 提取角色信息
        $roleIds = $data['roles'] ?? [];
        unset($data['roles']);

        // 设置默认值
        $data['status'] = $data['status'] ?? true;
        $data['deleted'] = false;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        // 创建管理员（使用模型的方法，因为需要密码加密）
        $admin = $this->model->createAdmin($data);

        if (!$admin) {
            throw new ApiException(Code::SYSTEM_ERROR, '创建管理员失败');
        }

        // 分配角色
        if (!empty($roleIds)) {
            $this->validateAndAssignRoles($admin, $roleIds);
        }

        // 重新加载管理员信息，包括角色关联
        return $admin->fresh(['roles']);
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
        // 提取角色信息
        $roleIds = null;
        if (isset($data['roles'])) {
            $roleIds = $data['roles'];
            unset($data['roles']);
        }

        // 更新管理员基本信息
        $admin = parent::update($id, $data);

        // 处理角色更新
        if ($roleIds !== null) {
            $this->validateAndAssignRoles($admin, $roleIds);
        }

        // 重新加载管理员信息，包括角色关联
        return $admin->fresh(['roles']);
    }

    /**
     * 删除管理员（软删除）
     * @param int $id 管理员ID
     * @return bool
     * @throws ApiException
     */
    public function deleteAdmin(int $id): bool
    {
        $admin = $this->model->find($id);
        if ($admin && $admin->id == 1) {
            throw new ApiException(Code::FORBIDDEN, '不允许删除超级管理员');
        }
        return parent::delete($id);
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
        
        // 使用统一的验证和分配方法
        $this->validateAndAssignRoles($admin, $roleIds);
        
        return true;
    }

    /**
     * 验证并分配角色（统一的角色分配逻辑）
     * @param Admin $admin 管理员实例
     * @param array $roleIds 角色ID数组
     * @return void
     * @throws ApiException
     */
    private function validateAndAssignRoles(Admin $admin, array $roleIds): void
    {
        // 验证角色是否存在且有效（一次性批量查询，提升性能）
        if (!empty($roleIds)) {
            $roleModel = ModelFactory::role();
            $existingRoles = $roleModel
                ->whereIn('id', $roleIds)
                ->where('status', 1)
                ->pluck('id')
                ->toArray();
            
            // 检查是否有无效的角色ID
            $invalidRoleIds = array_diff($roleIds, $existingRoles);
            if (!empty($invalidRoleIds)) {
                throw new ApiException(
                    Code::ROLE_NOT_FOUND, 
                    '角色不存在或已禁用: ' . implode(',', $invalidRoleIds)
                );
            }
        }
        
        // 调用模型的 assignRoles 方法（内部会检查 id=1 的限制）
        try {
            $admin->assignRoles($roleIds);
        } catch (ApiException $e) {
            // 直接抛出模型层的异常（如系统管理员限制）
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException(Code::SYSTEM_ERROR, '分配角色失败: ' . $e->getMessage());
        }
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
        // 检查是否包含超级管理员
        if (in_array(1, $ids)) {
            throw new ApiException(Code::FORBIDDEN, '不允许删除超级管理员');
        }

        return parent::batchDelete($ids);
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