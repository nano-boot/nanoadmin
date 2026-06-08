<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\common\IpLocation;
use plugin\theadmin\app\common\JwtUtil;
use plugin\theadmin\app\model\Admin;
use plugin\theadmin\app\model\ModelFactory;
use plugin\theadmin\app\service\LogLoginService;

/**
 * 认证服务类
 */
class AuthService
{
    /**
     * 超管权限查询返回结构中的全量放行标记。
     */
    public const PERMISSION_SCOPE_ALLOW_ALL = 'allow_all';

    /**
     * 普通权限查询返回结构中的精确权限标记。
     */
    public const PERMISSION_SCOPE_LIMITED = 'limited';

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
        $this->recordLoginLog($admin->id, $admin->username, $ip, $userAgent, true, '登录成功');

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
     * 获取管理员角色编码列表
     * @param int $adminId 管理员ID
     * @return array
     * @throws ApiException
     */
    public function getAdminRoleCodes(int $adminId): array
    {
        $admin = Admin::with(['roles'])->find($adminId);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }

        $codes = [];
        foreach ($admin->roles as $role) {
            if (!$role->isActive()) {
                continue;
            }

            $code = trim((string)($role->code ?? ''));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        return array_keys($codes);
    }

    /**
     * 获取管理员按钮权限码列表
     * @param int $adminId 管理员ID
     * @return array
     */
    public function getAdminButtonCodes(int $adminId): array
    {
        return $this->getAdminPermissionCodes($adminId);
    }

    /**
     * 获取管理员统一权限范围。
     *
     * 返回结构：
     * - scope: allow_all|limited
     * - codes: string[]
     *
     * @param int $adminId 管理员ID
     * @return array{scope:string,codes:array<int,string>}
     * @throws ApiException
     */
    public function getAdminPermissionScope(int $adminId): array
    {
        $admin = Admin::with(['roles.permissions'])->find($adminId);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }

        $isSuperAdmin = $admin->roles->contains('code', 'R_SUPER');
        if ($isSuperAdmin) {
            return [
                'scope' => self::PERMISSION_SCOPE_ALLOW_ALL,
                'codes' => [],
            ];
        }

        $codes = [];
        foreach ($admin->roles as $role) {
            if (!$role->isActive() || !isset($role->permissions)) {
                continue;
            }

            foreach ($role->permissions as $permission) {
                $code = trim((string)($permission->code ?? ''));
                if ($code !== '') {
                    $codes[$code] = true;
                }
            }
        }

        return [
            'scope' => self::PERMISSION_SCOPE_LIMITED,
            'codes' => array_keys($codes),
        ];
    }

    /**
     * 获取管理员统一权限码列表
     * @param int $adminId 管理员ID
     * @return array
     * @throws ApiException
     */
    public function getAdminPermissionCodes(int $adminId): array
    {
        return $this->getAdminPermissionScope($adminId)['codes'];
    }

    /**
     * 获取管理员权限列表
     * @param int $adminId 管理员ID
     * @return array
     * @throws ApiException
     */
    public function getAdminPermissions(int $adminId): array
    {
        $admin = Admin::with(['roles.permissions'])->find($adminId);
        if (!$admin) {
            throw new ApiException(Code::ADMIN_NOT_FOUND, '管理员不存在');
        }

        $permissions = [];
        foreach ($admin->roles as $role) {
            if (!$role->isActive() || !isset($role->permissions)) {
                continue;
            }

            foreach ($role->permissions as $permission) {
                $code = trim((string)($permission->code ?? ''));
                if ($code === '') {
                    continue;
                }

                $permission->code = $code;
                $permissions[$code] = $permission;
            }
        }

        return array_values($permissions);
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
     * 记录登录日志（伪异步：直接写入 + 异常捕获，不阻塞登录流程）
     *
     * @param int $adminId 管理员ID
     * @param string $username 用户名
     * @param string $ip IP地址
     * @param string $userAgent User-Agent
     * @param bool $success 是否成功
     * @param string $loginInfo 登录信息（成功：登录成功 / 失败：失败原因）
     * @return void
     */
    private function recordLoginLog(
        int $adminId,
        string $username,
        string $ip,
        string $userAgent = '',
        bool $success = true,
        string $loginInfo = ''
    ): void {
        try {
            $ipLocation = new IpLocation();
            $location = $ipLocation->get($ip);

            $loginLogService = new LogLoginService(ModelFactory::log_login());
            $loginLogService->recordLogin([
                'admin_id' => $adminId,
                'username' => $username,
                'ip' => $ip,
                'user_agent' => mb_substr($userAgent, 0, 500),
                'location' => $location,
                'status' => $success ? 1 : 0,
                'login_info' => $loginInfo,
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