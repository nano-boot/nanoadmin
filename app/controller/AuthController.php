<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\service\AuthService;

/**
 * 认证控制器
 */
class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * 管理员登录
     * POST /sys/auth/login
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response
    {
        try {
            $username = $request->post('username', '');
            $password = $request->post('password', '');
            $ip = $request->getRealIp();

            // 验证必填参数
            if (empty($username) || empty($password)) {
                return R::error('用户名或密码不能为空',Code::PARAMETER_ERROR->value);
            }

            // 执行登录
            $result = $this->authService->login($username, $password, $ip);
            return R::ok( '登录成功',$result);
        } catch (ApiException $e) {
            return R::error($e->getMessage(),$e->getCode());
        } catch (\Exception $e) {
            return R::error('登录失败：' . $e->getMessage(),Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 管理员退出登录
     * POST /sys/auth/logout
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout($request->admin);
        return R::ok('退出成功');
    }

    /**
     * 获取当前用户信息
     * GET /sys/auth/info
     * @param Request $request
     * @return Response
     */
    public function info(Request $request): Response
    {
        $admin = $request->admin;
        $profile = [
            'id' => $admin->id,
            'username' => $admin->username,
            'nickname' => $admin->nickname,
            'phone' => $admin->phone,
            'email' => $admin->email,
            'avatar' => $admin->avatar,
            'status' => $admin->status,
            'created_at' => $admin->created_at,
            'updated_at' => $admin->updated_at,
            'last_login_at' => $admin->last_login_at,
            'last_login_ip' => $admin->last_login_ip,
            'roles' => $admin->roles->pluck('code')->toArray(),
        ];

        return R::ok('获取用户信息成功',$profile);
    }

    /**
     * 获取当前用户权限列表
     * GET /sys/auth/permissions
     * @param Request $request
     * @return Response
     */
    public function permissions(Request $request): Response
    {
        $permissions =  $request->admin->getPermissions();
        return R::ok('获取权限列表成功',$permissions);
    }

    /**
     * 获取当前用户菜单列表
     * GET /sys/auth/menus
     * @param Request $request
     * @return Response
     */
    public function menus(Request $request): Response
    {
        $menus = $request->admin->getMenus();
        return R::ok('获取菜单列表成功',$menus);
    }

    /**
     * 刷新Token
     * POST /sys/auth/refresh
     * @param Request $request
     * @return Response
     */
    public function refresh(Request $request): Response
    {
        try {
            $refreshToken = $request->post('refresh_token', '');
            if (empty($refreshToken)) {
                return R::error(Code::REFRESH_TOKEN_MISSING);
            }
            $tokenData = $this->authService->refreshToken($refreshToken);
            return R::ok('刷新令牌成功',$tokenData);
        } catch (ApiException $e) {
            return R::error($e->getMessage(),$e->getCode());
        } catch (\Exception $e) {
            return R::error('刷新令牌失败：' . $e->getMessage(),Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 检查Token有效性
     * POST /sys/auth/check
     * @param Request $request
     * @return Response
     */
    public function check(Request $request)
    {
        try {
            $token = $this->getTokenFromRequest($request);
            
            if (empty($token)) {
                return R::error(Code::TOKEN_MISSING);
            }

            $payload = $this->authService->validateToken($token);
            $remainingTime = $this->authService->getTokenRemainingTime($token);

            $result = [
                'valid' => true,
                'remaining_time' => $remainingTime,
                'user_id' => $payload['user_id'] ?? 0,
                'expires_at' => $payload['exp'] ?? 0,
            ];

            return R::ok('Token有效',$result);

        } catch (ApiException $e) {
            return R::error($e->getMessage(),$e->getCode());
        } catch (\Exception $e) {
            return R::error('Token验证失败：' . $e->getMessage(),Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 从请求中获取Token
     * @param Request $request
     * @return string
     */
    private function getTokenFromRequest(Request $request): string
    {
        // 优先从Authorization头获取
        $authorization = $request->header('Authorization', '');
        if (!empty($authorization) && strpos($authorization, 'Bearer ') === 0) {
            return substr($authorization, 7);
        }

        // 从POST参数获取
        $token = $request->post('token', '');
        if (!empty($token)) {
            return $token;
        }

        // 从GET参数获取
        $token = $request->get('token', '');
        if (!empty($token)) {
            return $token;
        }

        return '';
    }
}