<?php

namespace plugin\nanoadmin\app\contract;

use Illuminate\Pagination\LengthAwarePaginator;
use plugin\nanoadmin\app\model\Admin;

/**
 * 管理员服务接口
 * 定义管理员服务的标准接口
 */
interface AdminServiceInterface
{
    /**
     * 获取管理员列表
     * @param array $params 查询参数
     * @return array
     */
    public function getAdminList(array $params): LengthAwarePaginator;

    /**
     * 根据ID获取管理员信息
     * @param int $id 管理员ID
     * @return Admin|null
     */
    public function getAdminById(int $id): ?Admin;

    /**
     * 创建管理员
     * @param array $data 管理员数据
     * @return Admin
     */
    public function createAdmin(array $data): Admin;

    /**
     * 更新管理员信息
     * @param int $id 管理员ID
     * @param array $data 更新数据
     * @return Admin
     */
    public function updateAdmin(int $id, array $data): Admin;

    /**
     * 删除管理员
     * @param int $id 管理员ID
     * @return bool
     */
    public function deleteAdmin(int $id): bool;

    /**
     * 批量删除管理员
     * @param array $ids 管理员ID数组
     * @return int 删除数量
     */
    public function batchDeleteAdmins(array $ids): int;

    /**
     * 更新管理员状态
     * @param int $id 管理员ID
     * @param int $status 状态值
     * @return bool
     */
    public function updateAdminStatus(int $id, int $status): bool;

    /**
     * 重置管理员密码
     * @param int $id 管理员ID
     * @param string $password 新密码
     * @return bool
     */
    public function resetAdminPassword(int $id, string $password): bool;
}
