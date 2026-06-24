<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\common\JwtUtil;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\schema\auth\AuthInfoResponse;
use plugin\nanoadmin\app\schema\auth\CheckRequest;
use plugin\nanoadmin\app\schema\auth\CheckResponse;
use plugin\nanoadmin\app\schema\auth\LoginRequest;
use plugin\nanoadmin\app\schema\auth\LoginResponse;
use plugin\nanoadmin\app\schema\auth\PermissionItem;
use plugin\nanoadmin\app\schema\auth\RefreshRequest;
use plugin\nanoadmin\app\schema\auth\TokenResponse;
use plugin\nanoadmin\app\service\AuthService;
use plugin\nanoadmin\app\validator\auth\AuthValidator;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 认证控制器
 *
 * 路由：所有方法均通过类级 / 方法级 OA 注解自动注册，详见 OpenApiRouteRegister。
 *
 * 中间件策略：
 *  - 类级默认走 AuthMiddleware（要求登录态）
 *  - login 方法用 #[Middleware()] 覆盖为"无中间件"
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
#[OA\Tag(name: '认证', description: '管理员登录、登出、用户信息、Token 刷新等认证相关接口')]
#[Middleware(AuthMiddleware::class)]
class AuthController
{
    protected AuthService $authService;
    protected AuthValidator $validator;

    /**
     * 构建 /auth/info 返回契约。
     *
     * 约定：
     * - permissionScope：后端统一权限范围，显式区分超管全放行与普通精确授权
     * - permissionCodes：后端内部统一权限码数组
     * - buttons：框架兼容保留字段，值来源于后端统一权限码
     * - backend 模式下前端正式按钮权限来源仍为动态路由中的 route.meta.authList
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
        $this->authService = $authService;
        $this->validator = new AuthValidator();
    }

    /**
     * 管理员登录
     *
     * 无需认证中间件（公开接口）
     */
    #[OA\Post(
        path: '/sys/auth/login',
        summary: '管理员登录',
        tags: ['认证'],
        x: [OpenApiModifier::X_REQUEST_BODY => LoginRequest::class]
    )]
    #[Middleware()]
    #[DataResponse(schema: LoginResponse::class)]
    public function login(Request $request): Response
    {
        $data = $this->validator->scene('login')->setPost()->check();
        $ip = $request->getRealIp() ?? '';
        $userAgent = $request->header('User-Agent', '');

        $result = $this->authService->login(
            $data['username'] ?? '',
            $data['password'] ?? '',
            $ip,
            $userAgent
        );
        return R::success($result, '登录成功');
    }

    /**
     * 管理员登出
     */
    #[OA\Post(
        path: '/sys/auth/logout',
        summary: '管理员登出',
        tags: ['认证']
    )]
    #[DataResponse()]
    public function logout(Request $request): Response
    {
        $this->authService->logout($request->admin);
        return R::ok('登出成功');
    }

    /**
     * 获取当前用户信息
     */
    #[OA\Get(
        path: '/sys/auth/info',
        summary: '获取当前管理员信息',
        tags: ['认证']
    )]
    #[DataResponse(schema: AuthInfoResponse::class)]
    public function info(Request $request): Response
    {
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
    }

    /**
     * 刷新 Token
     */
    #[OA\Post(
        path: '/sys/auth/refresh',
        summary: '刷新 Token',
        tags: ['认证'],
        x: [OpenApiModifier::X_REQUEST_BODY => RefreshRequest::class]
    )]
    #[DataResponse(schema: TokenResponse::class)]
    public function refresh(Request $request): Response
    {
        $data = $this->validator->validateRefreshData($request->post());
        $result = $this->authService->refreshToken($data['refresh_token'] ?? '');
        return R::success($result, 'Token刷新成功');
    }

    /**
     * 获取当前用户的权限列表
     */
    #[OA\Get(
        path: '/sys/auth/permissions',
        summary: '获取当前管理员权限列表',
        tags: ['认证']
    )]
    #[DataResponse()]
    public function permissions(Request $request): Response
    {
        $admin = $request->admin;
        if (!$admin) {
            return R::error('用户未登录', 401);
        }

        $permissions = $this->authService->getAdminPermissions($admin->id);
        return R::success($permissions, '获取权限列表成功');
    }

    /**
     * 获取当前用户的菜单列表
     */
    #[OA\Get(
        path: '/sys/auth/menus',
        summary: '获取当前管理员菜单列表',
        tags: ['认证']
    )]
    #[DataResponse()]
    public function menus(Request $request): Response
    {
        $admin = $request->admin;
        if (!$admin) {
            return R::error('用户未登录', 401);
        }

        $menus = $this->authService->getAdminMenus($admin->id);
        return R::success($menus, '获取菜单列表成功');
    }

    /**
     * 检查 Token 有效性
     */
    #[OA\Post(
        path: '/sys/auth/check',
        summary: '检查 Token 有效性',
        tags: ['认证'],
        x: [OpenApiModifier::X_REQUEST_BODY => CheckRequest::class]
    )]
    #[DataResponse(schema: CheckResponse::class)]
    public function check(Request $request): Response
    {
        $data = $this->validator->validateCheckData($request->post());
        $token = $data['token'] ?? '';

        try {
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
