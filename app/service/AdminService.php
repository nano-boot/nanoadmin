<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\ErrorCode;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\Admin;
use think\Paginator;

/**
 * 管理员服务类
 */
class AdminService
{
    /**
     * 获取管理员列表
     * @param array $params 查询参数
     * @return Paginator
     */
    public function getAdminList(array $params = []): Paginator
    {
        $adminModel = ModelFactory::admin();
        
        // 构建查询条件
        $where = [];
        
        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $where['status'] = (bool)$params['status'];
        }
        
        // 软删除会自动排除已删除的记录，无需手动设置条件
        
        // 分页参数
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 15;
        
        // 搜索条件
        $searchParams = [];
        if (!empty($params['username'])) {
            $searchParams['username'] = $params['username'];
        }
        if (!empty($params['nickname'])) {
            $searchParams['nickname'] = $params['nickname'];
        }
        if (!empty($params['phone'])) {
            $searchParams['phone'] = $params['phone'];
        }
        
        return $adminModel->getListWithRoles(array_merge($where, $searchParams), $page, $limit);
    }

    /**
     * 根据ID获取管理员详情
     * @param int $id 管理员ID
     * @return Admin
     * @throws ApiException
     */
    public function getAdminById(int $id): Admin
    {
        $adminModel = ModelFactory::admin();
        $admin = $adminModel->with('roles')->find($id);
        
        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
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
        // 数据验证
        $this->validateAdminData($data, true);
        
        $adminModel = ModelFactory::admin();
        
        // 检查用户名是否已存在
        if ($adminModel->where('username', $data['username'])->find()) {
            throw new ApiException(ErrorCode::DUPLICATE_NAME, '用户名已存在');
        }
        
        // 检查手机号是否已存在（如果提供了手机号）
        if (!empty($data['phone'])) {
            if ($adminModel->where('phone', $data['phone'])->find()) {
                throw new ApiException(ErrorCode::DUPLICATE_NAME, '手机号已存在');
            }
        }
        
        // 检查邮箱是否已存在（如果提供了邮箱）
        if (!empty($data['email'])) {
            if ($adminModel->where('email', $data['email'])->find()) {
                throw new ApiException(ErrorCode::DUPLICATE_NAME, '邮箱已存在');
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
            throw new ApiException(ErrorCode::SYSTEM_ERROR, '创建管理员失败');
        }
        
        return $admin;
    }

    /**
     * 更新管理员
     * @param int $id 管理员ID
     * @param array $data 更新数据
     * @return bool
     * @throws ApiException
     */
    public function updateAdmin(int $id, array $data): bool
    {
        // 数据验证
        $this->validateAdminData($data, false);
        
        $adminModel = ModelFactory::admin();
        
        // 检查管理员是否存在
        $admin = $adminModel->find($id);
        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 检查用户名是否已被其他管理员使用
        if (!empty($data['username'])) {
            $existingAdmin = $adminModel->where('username', $data['username'])
                ->where('id', '<>', $id)
                ->find();
            if ($existingAdmin) {
                throw new ApiException(ErrorCode::DUPLICATE_NAME, '用户名已存在');
            }
        }
        
        // 检查手机号是否已被其他管理员使用
        if (!empty($data['phone'])) {
            $existingAdmin = $adminModel->where('phone', $data['phone'])
                ->where('id', '<>', $id)
                ->find();
            if ($existingAdmin) {
                throw new ApiException(ErrorCode::DUPLICATE_NAME, '手机号已存在');
            }
        }
        
        // 检查邮箱是否已被其他管理员使用
        if (!empty($data['email'])) {
            $existingAdmin = $adminModel->where('email', $data['email'])
                ->where('id', '<>', $id)
                ->find();
            if ($existingAdmin) {
                throw new ApiException(ErrorCode::DUPLICATE_NAME, '邮箱已存在');
            }
        }
        
        // 更新时间
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // 更新管理员
        $result = $adminModel->updateAdmin($id, $data);
        
        if (!$result) {
            throw new ApiException(ErrorCode::SYSTEM_ERROR, '更新管理员失败');
        }
        
        return true;
    }

    /**
     * 删除管理员（软删除）
     * @param int $id 管理员ID
     * @return bool
     * @throws ApiException
     */
    public function deleteAdmin(int $id): bool
    {
        $adminModel = ModelFactory::admin();
        
        // 检查管理员是否存在
        $admin = $adminModel->find($id);
        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 软删除
        $result = $adminModel->destroy($id);
        
        if ($result === false) {
            throw new ApiException(ErrorCode::SYSTEM_ERROR, '删除管理员失败');
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
        $adminModel = ModelFactory::admin();
        
        // 检查管理员是否存在
        $admin = $adminModel->find($id);
        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 更新状态
        $result = $adminModel->where('id', $id)->update([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result === false) {
            throw new ApiException(ErrorCode::SYSTEM_ERROR, '更新管理员状态失败');
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
        $adminModel = ModelFactory::admin();
        
        // 检查管理员是否存在
        $admin = $adminModel->find($adminId);
        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 验证角色是否存在
        if (!empty($roleIds)) {
            $roleModel = ModelFactory::role();
            $existingRoles = $roleModel->whereIn('id', $roleIds)->where('status', true)->column('id');
            
            $invalidRoleIds = array_diff($roleIds, $existingRoles);
            if (!empty($invalidRoleIds)) {
                throw new ApiException(ErrorCode::ROLE_NOT_FOUND, '角色不存在: ' . implode(',', $invalidRoleIds));
            }
        }
        
        // 分配角色
        $result = $admin->assignRoles($roleIds);
        
        if ($result === false) {
            throw new ApiException(ErrorCode::SYSTEM_ERROR, '分配角色失败');
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
        $adminModel = ModelFactory::admin();
        
        // 检查管理员是否存在
        $admin = $adminModel->find($adminId);
        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        return $admin->getPermissions();
    }

    /**
     * 获取管理员菜单列表
     * @param int $adminId 管理员ID
     * @return array
     * @throws ApiException
     */
    public function getAdminMenus(int $adminId): array
    {
        $adminModel = ModelFactory::admin();
        
        // 检查管理员是否存在
        $admin = $adminModel->find($adminId);
        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
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
            $adminModel = ModelFactory::admin();
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
            $adminModel = ModelFactory::admin();
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
        $adminModel = ModelFactory::admin();
        
        // 检查管理员是否存在
        $admin = $adminModel->find($adminId);
        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
        }
        
        // 如果提供了旧密码，需要验证
        if (!empty($oldPassword)) {
            if (!$admin->verifyPassword($oldPassword)) {
                throw new ApiException(ErrorCode::PASSWORD_ERROR, '原密码错误');
            }
        }
        
        // 密码强度验证
        if (strlen($newPassword) < 6) {
            throw new ApiException(ErrorCode::PARAMETER_ERROR, '密码长度不能少于6位');
        }
        
        // 更新密码
        $result = $adminModel->where('id', $adminId)->update([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result === false) {
            throw new ApiException(ErrorCode::SYSTEM_ERROR, '修改密码失败');
        }
        
        return true;
    }

    /**
     * 验证管理员数据
     * @param array $data 管理员数据
     * @param bool $isCreate 是否为创建操作
     * @throws ApiException
     */
    private function validateAdminData(array $data, bool $isCreate = false): void
    {
        // 创建时必须提供用户名和密码
        if ($isCreate) {
            if (empty($data['username'])) {
                throw new ApiException(ErrorCode::PARAMETER_ERROR, '用户名不能为空');
            }
            if (empty($data['password'])) {
                throw new ApiException(ErrorCode::PARAMETER_ERROR, '密码不能为空');
            }
        }
        
        // 用户名格式验证
        if (!empty($data['username'])) {
            if (strlen($data['username']) < 3 || strlen($data['username']) > 20) {
                throw new ApiException(ErrorCode::PARAMETER_ERROR, '用户名长度必须在3-20个字符之间');
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                throw new ApiException(ErrorCode::PARAMETER_ERROR, '用户名只能包含字母、数字和下划线');
            }
        }
        
        // 密码格式验证
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                throw new ApiException(ErrorCode::PARAMETER_ERROR, '密码长度不能少于6位');
            }
        }
        
        // 昵称验证
        if (!empty($data['nickname'])) {
            if (strlen($data['nickname']) > 50) {
                throw new ApiException(ErrorCode::PARAMETER_ERROR, '昵称长度不能超过50个字符');
            }
        }
        
        // 手机号验证
        if (!empty($data['phone'])) {
            if (!preg_match('/^1[3-9]\d{9}$/', $data['phone'])) {
                throw new ApiException(ErrorCode::PARAMETER_ERROR, '手机号格式不正确');
            }
        }
        
        // 邮箱验证
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new ApiException(ErrorCode::PARAMETER_ERROR, '邮箱格式不正确');
            }
        }
    }

    /**
     * 批量删除管理员
     * @param array $ids 管理员ID数组
     * @return bool
     * @throws ApiException
     */
    public function batchDeleteAdmins(array $ids): bool
    {
        if (empty($ids)) {
            throw new ApiException(ErrorCode::PARAMETER_ERROR, '请选择要删除的管理员');
        }
        
        $adminModel = ModelFactory::admin();
        
        // 检查管理员是否存在
        $existingAdmins = $adminModel->whereIn('id', $ids)->column('id');
        $invalidIds = array_diff($ids, $existingAdmins);
        
        if (!empty($invalidIds)) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在: ' . implode(',', $invalidIds));
        }
        
        // 批量软删除
        $result = $adminModel->destroy($ids);
        
        if ($result === false) {
            throw new ApiException(ErrorCode::SYSTEM_ERROR, '批量删除管理员失败');
        }
        
        return true;
    }
}