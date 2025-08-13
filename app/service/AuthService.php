<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\ErrorCode;
use plugin\theadmin\app\common\JwtUtil;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\model\Admin;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 认证服务类
 */
class AuthService
{
    /**
     * 管理员登录
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $ip 登录IP
     * @return array
     * @throws ApiException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function login(string $username, string $password, string $ip = ''): array
    {
        // 参数验证
        if (empty($username) || empty($password)) {
            throw new ApiException(ErrorCode::PARAMETER_ERROR, '用户名和密码不能为空');
        }

        // 查找管理员
        $adminModel = ModelFactory::admin();

        $admin = $adminModel->where('username', $username)->find();

        if (!$admin) {
            throw new ApiException(ErrorCode::LOGIN_FAILED, '用户名或密码错误');
        }

        // 检查账户状态
        if (!$admin->status) {
            throw new ApiException(ErrorCode::ACCOUNT_DISABLED, '账户已被禁用');
        }

        // 验证密码
        if (!$admin->verifyPassword($password)) {
            throw new ApiException(ErrorCode::LOGIN_FAILED, '用户名或密码错误');
        }

        // 更新最后登录信息
        $admin->updateLastLogin($ip);

        // 生成Token
        $tokenData = JwtUtil::generateTokenPair($admin->id, [
            'username' => $admin->username,
            'nickname' => $admin->nickname,
        ]);

        // 记录登录日志
        $this->recordLoginLog($admin->id, $ip, true);

        return [
            'admin' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'nickname' => $admin->nickname,
                'phone' => $admin->phone,
                'email' => $admin->email,
                'avatar' => $admin->avatar,
                'status' => $admin->status,
            ],
            'token' => $tokenData,
        ];
    }

    /**
     * 管理员退出登录
     * @param string $token 访问Token
     * @return bool
     */
    public function logout(string $token): bool
    {
        try {
            // 验证Token并获取用户信息
            $payload = JwtUtil::verifyToken($token);
            $adminId = $payload['user_id'] ?? 0;

            if ($adminId) {
                // 记录退出日志
                $this->recordLoginLog($adminId, '', false, '主动退出');
            }

            // 注意：JWT是无状态的，这里只是记录日志
            // 实际的Token失效需要通过过期时间或者黑名单机制实现
            return true;
        } catch (ApiException $e) {
            // Token无效也算退出成功
            return true;
        }
    }

    /**
     * 验证Token
     * @param string $token JWT Token
     * @return array 返回Token载荷数据
     * @throws ApiException
     */
    public function validateToken(string $token): array
    {
        return JwtUtil::verifyToken($token);
    }

    /**
     * 生成Token
     * @param Admin $admin 管理员对象
     * @return array
     */
    public function generateToken(Admin $admin): array
    {
        return JwtUtil::generateTokenPair($admin->id, [
            'username' => $admin->username,
            'nickname' => $admin->nickname,
        ]);
    }

    /**
     * 刷新Token
     * @param string $refreshToken 刷新Token
     * @return array
     * @throws ApiException
     */
    public function refreshToken(string $refreshToken): array
    {
        // 验证刷新Token
        $payload = JwtUtil::verifyToken($refreshToken);

        // 检查是否为刷新Token
        if (!JwtUtil::isRefreshToken($payload)) {
            throw new ApiException(ErrorCode::TOKEN_INVALID, '无效的刷新Token');
        }

        $adminId = $payload['user_id'] ?? 0;
        if (!$adminId) {
            throw new ApiException(ErrorCode::TOKEN_INVALID, 'Token中缺少用户信息');
        }

        // 验证管理员是否存在且状态正常
        $adminModel = ModelFactory::admin();
        $admin = $adminModel->find($adminId);

        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
        }

        if (!$admin->status) {
            throw new ApiException(ErrorCode::ACCOUNT_DISABLED, '账户已被禁用');
        }

        // 生成新的Token对
        return JwtUtil::generateTokenPair($admin->id, [
            'username' => $admin->username,
            'nickname' => $admin->nickname,
        ]);
    }

    /**
     * 根据Token获取管理员信息
     * @param string $token 访问Token
     * @return Admin
     * @throws ApiException
     */
    public function getAdminByToken(string $token): Admin
    {
        // 验证Token
        $payload = JwtUtil::verifyToken($token);

        // 检查是否为访问Token
        if (!JwtUtil::isAccessToken($payload)) {
            throw new ApiException(ErrorCode::TOKEN_INVALID, '无效的访问Token');
        }

        $adminId = $payload['user_id'] ?? 0;
        if (!$adminId) {
            throw new ApiException(ErrorCode::TOKEN_INVALID, 'Token中缺少用户信息');
        }

        // 获取管理员信息
        $adminModel = ModelFactory::admin();
        $admin = $adminModel->find($adminId);

        if (!$admin) {
            throw new ApiException(ErrorCode::ADMIN_NOT_FOUND, '管理员不存在');
        }

        if (!$admin->status) {
            throw new ApiException(ErrorCode::ACCOUNT_DISABLED, '账户已被禁用');
        }

        return $admin;
    }

    /**
     * 获取管理员权限列表
     * @param int $adminId 管理员ID
     * @return array
     */
    public function getAdminPermissions(int $adminId): array
    {
        $adminModel = ModelFactory::admin();
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
     */
    public function getAdminMenus(int $adminId): array
    {
        $adminModel = ModelFactory::admin();
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
    public function checkPermission(int $adminId, string $permission): bool
    {
        $adminModel = ModelFactory::admin();
        $admin = $adminModel->find($adminId);

        if (!$admin) {
            return false;
        }

        return $admin->hasPermission($permission);
    }

    /**
     * 记录登录日志
     * @param int $adminId 管理员ID
     * @param string $ip IP地址
     * @param bool $success 是否成功
     * @param string $remark 备注
     * @return void
     */
    private function recordLoginLog(int $adminId, string $ip, bool $success = true, string $remark = ''): void
    {
        // 这里可以实现登录日志记录功能
        // 可以记录到数据库或日志文件
        // 暂时使用简单的日志记录
        $logData = [
            'admin_id' => $adminId,
            'ip' => $ip,
            'success' => $success,
            'remark' => $remark,
            'time' => date('Y-m-d H:i:s'),
        ];

        // 可以扩展为写入数据库或其他存储方式
        error_log('Login Log: ' . json_encode($logData, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 验证Token格式
     * @param string $token Token字符串
     * @return bool
     */
    public function isValidTokenFormat(string $token): bool
    {
        // 简单的Token格式验证
        return !empty($token) && strlen($token) > 10;
    }

    /**
     * 获取Token剩余有效时间
     * @param string $token JWT Token
     * @return int 剩余秒数，-1表示已过期或无效
     */
    public function getTokenRemainingTime(string $token): int
    {
        return JwtUtil::getTokenRemainingTime($token);
    }
}