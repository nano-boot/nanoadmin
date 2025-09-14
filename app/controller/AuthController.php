<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\R;
use plugin\theadmin\app\service\AuthService;
use plugin\theadmin\app\validator\AdminValidator;
use plugin\theadmin\app\validator\AuthValidator;
use support\Request;
use support\Response;

/**
 * 认证控制器
 * 
 * @author TheAdmin Team
 * @since 1.0.0
 */
class AuthController
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        new AuthValidator();
        $this->authService = $authService;
    }

    /**
     * 管理员登录
     *
     * @param Request $request
     * @return Response
     * @throws ApiException
     */
    public function login(Request $request): Response
    {
        // 管理员登录
        $result = $this->authService->login(...$request->only(['username', 'password']));
        return R::success($result, '登录成功');
    }

    /**
     * 管理员登出
     * 
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout($request->admin);
        return R::ok('登出成功');
    }

    /**
     * 获取当前用户信息
     * 
     * @param Request $request
     * @return Response
     */
    public function info(Request $request): Response
    {
        try {
            $admin = $request->admin;
            if (!$admin) {
                return R::error('用户未登录', 401);
            }
            
            return R::success([
                'id' => $admin->id,
                'username' => $admin->username,
                'nickname' => $admin->nickname,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'avatar' => $admin->avatar,
                'status' => $admin->status,
                'gender' => $admin->gender,
                'last_login_time' => $admin->last_login_time,
                'created_at' => $admin->created_at
            ], '获取成功');
            
        } catch (\Exception $e) {
            return R::error($e->getMessage());
        }
    }

    /**
     * 刷新Token
     * 
     * @param Request $request
     * @return Response
     */
    public function refresh(Request $request): Response
    {
        try {
            $result = $this->authService->refreshToken($request);
            return R::success($result, 'Token刷新成功');
        } catch (\Exception $e) {
            return R::error($e->getMessage());
        }
    }
}