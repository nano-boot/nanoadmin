<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\common\JwtUtil;
use plugin\theadmin\app\model\Admin;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\service\LoginLogService;

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
     * @param string $userAgent User-Agent
     * @return array
     * @throws ApiException
     */
    public function login(string $username, string $password, string $ip = '', string $userAgent = ''): array
    {
        // 查找管理员
        $admin = Admin::where('username', $username)
            ->where('status', 1)
            ->with('roles')
            ->first();

        if (!$admin) {
            $this->recordLoginLog(0, $username, $ip, $userAgent, false, '用户不存在或已被禁用');
            throw new ApiException(Code::LOGIN_FAILED, '用户名或密码错误');
        }

        // 验证密码
        if (!$admin->verifyPassword($password)) {
            $this->recordLoginLog($admin->id, $username, $ip, $userAgent, false, '密码错误');
            throw new ApiException(Code::LOGIN_FAILED, '用户名或密码错误');
        }

        // 更新最后登录信息
        $admin->updateLastLogin($ip);

        // 生成Token
        $tokenData = JwtUtil::generateTokenPair($admin->id, [
            'username' => $admin->username,
            'nickname' => $admin->nickname,
        ]);

        // 记录登录日志
        $this->recordLoginLog($admin->id, $admin->username, $ip, $userAgent, true);

        return [
            'user' => [
                'id' => $admin->id,
                'username' => $admin->username,
                'nickname' => $admin->nickname,
                'phone' => $admin->phone,
                'email' => $admin->email,
                'avatar' => $admin->avatar,
                'status' => $admin->status,
                'roles' => $admin->roles->pluck('code')->toArray(),
            ],
            'token' => $tokenData,
        ];
    }

    /**
     * 管理员退出登录
     * @param Admin $admin
     * @return bool
     */
    public function logout(Admin $admin): bool
    {
        $ip = $admin->last_login_ip ?? '';
        $this->recordLoginLog($admin->id, $admin->username, $ip, '', true, '主动退出');
        return true;
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
            throw new ApiException(Code::TOKEN_INVALID, '无效的刷新Token');
        }

        $adminId = $payload['user_id'] ?? 0;
        if (!$adminId) {
            throw new ApiException(Code::TOKEN_INVALID, 'Token中缺少用户信息');
        }

        // 验证管理员是否存在且状态正常
        $admin = Admin::find($adminId);

        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }

        if (!$admin->status) {
            throw new ApiException(Code::ACCOUNT_DISABLED, '账户已被禁用');
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
            throw new ApiException(Code::TOKEN_INVALID, '无效的访问Token');
        }

        $adminId = $payload['user_id'] ?? 0;
        if (!$adminId) {
            throw new ApiException(Code::TOKEN_INVALID, 'Token中缺少用户信息');
        }

        // 获取管理员信息
        $admin = Admin::find($adminId);

        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }

        if (!$admin->status) {
            throw new ApiException(Code::ACCOUNT_DISABLED, '账户已被禁用');
        }

        return $admin;
    }

    /**
     * 获取管理员权限列表
     * @param int $adminId 管理员ID
     * @return array
     * @throws ApiException
     */
    public function getAdminPermissions(int $adminId): array
    {
        $admin = Admin::find($adminId);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
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
        $admin = Admin::find($adminId);

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
    public function checkPermission(int $adminId, string $permission): bool
    {
        $admin = Admin::find($adminId);

        if (!$admin) {
            return false;
        }

        return $admin->hasPermission($permission);
    }

    /**
     * 记录登录日志
     * @param int $adminId 管理员ID
     * @param string $username 用户名
     * @param string $ip IP地址
     * @param string $userAgent User-Agent
     * @param bool $success 是否成功
     * @param string $failReason 失败原因
     * @return void
     */
    private function recordLoginLog(
        int $adminId,
        string $username,
        string $ip,
        string $userAgent = '',
        bool $success = true,
        string $failReason = ''
    ): void {
        try {
            $loginLogService = new LoginLogService(ModelFactory::login_log());
            $loginLogService->recordLogin([
                'admin_id' => $adminId,
                'username' => $username,
                'ip' => $ip,
                'user_agent' => mb_substr($userAgent, 0, 500),
                'location' => '',
                'status' => $success ? 1 : 0,
                'fail_reason' => $failReason,
                'login_time' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            error_log('LoginLog Error: ' . $e->getMessage());
        }
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