<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\JwtUtil;
use plugin\theadmin\app\common\R;
use plugin\theadmin\app\service\AuthService;
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

    /**
     * 构建 /auth/info 返回契约。
     *
     * 约定：
     * - permissionScope：后端统一权限范围，显式区分超管全放行与普通精确授权
     * - permissionCodes：后端内部统一权限码数组
     * - buttons：框架兼容保留字段，值来源于后端统一权限码
     * - backend 模式下前端正式按钮权限来源仍为动态路由中的 route.meta.authList
     *
     * @param object $admin 当前管理员对象
     * @param array $roleCodes 角色编码列表
     * @param array $permissionScope 后端统一权限范围
     * @return array
     */
    protected function buildInfoPayload(object $admin, array $roleCodes, array $permissionScope): array
    {
        return [
            'id' => $admin->id,
            'username' => $admin->username,
            'nickname' => $admin->nickname,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'avatar' => $admin->avatar,
            'status' => $admin->status,
            'gender' => $admin->gender,
            'last_login_time' => $admin->last_login_time,
            'created_at' => $admin->created_at,
            'roles' => $roleCodes,
            'permissionScope' => $permissionScope,
            'permissionCodes' => $permissionScope['codes'],
            'buttons' => $permissionScope['codes'],
        ];
    }

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
        $credentials = $request->only(['username', 'password']);
        $ip = $request->getRealIp() ?? '';
        $userAgent = $request->header('User-Agent', '');
        $result = $this->authService->login(
            $credentials['username'] ?? '',
            $credentials['password'] ?? '',
            $ip,
            $userAgent
        );
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

            $permissionScope = $this->authService->getAdminPermissionScope($admin->id);
            $roleCodes = $this->authService->getAdminRoleCodes($admin->id);

            return R::success(
                $this->buildInfoPayload($admin, $roleCodes, $permissionScope),
                '获取成功'
            );

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
            $refreshToken = $request->post('refresh_token', '');
            if (empty($refreshToken)) {
                return R::error('刷新Token不能为空');
            }
            $result = $this->authService->refreshToken($refreshToken);
            return R::success($result, 'Token刷新成功');
        } catch (\Exception $e) {
            return R::error($e->getMessage());
        }
    }

    /**
     * 获取当前用户的权限列表
     * 
     * @param Request $request
     * @return Response
     */
    public function permissions(Request $request): Response
    {
        try {
            $admin = $request->admin;
            if (!$admin) {
                return R::error('用户未登录', 401);
            }
            
            $permissions = $this->authService->getAdminPermissions($admin->id);
            
            return R::success($permissions, '获取权限列表成功');
        } catch (\Exception $e) {
            return R::error($e->getMessage());
        }
    }

    /**
     * 获取当前用户的菜单列表
     * 
     * @param Request $request
     * @return Response
     */
    public function menus(Request $request): Response
    {
        try {
            $admin = $request->admin;
            if (!$admin) {
                return R::error('用户未登录', 401);
            }
            
            $menus = $this->authService->getAdminMenus($admin->id);
            
            return R::success($menus, '获取菜单列表成功');
        } catch (\Exception $e) {
            return R::error($e->getMessage());
        }
    }

    /**
     * 检查 Token 有效性
     * 
     * @param Request $request
     * @return Response
     */
    public function check(Request $request): Response
    {
        try {
            $token = $request->post('token', '');
            if (empty($token)) {
                return R::error('Token不能为空');
            }
            
            $payload = JwtUtil::verifyToken($token);
            $remainingTime = JwtUtil::getTokenRemainingTime($token);
            
            return R::success([
                'valid' => true,
                'remaining_time' => $remainingTime,
                'user_id' => $payload['user_id'] ?? null,
            ], '检查成功');
        } catch (\Exception $e) {
            return R::success([
                'valid' => false,
                'remaining_time' => -1,
                'error' => $e->getMessage(),
            ], 'Token无效');
        }
    }
}