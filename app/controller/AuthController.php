<?php

namespace plugin\theadmin\app\controller;

use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiResponse;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\ErrorCode;
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

            // 执行登录
            $result = $this->authService->login($username, $password, $ip);
            $response = ApiResponse::success($result, '登录成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '登录失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 管理员退出登录
     * POST /sys/auth/logout
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request)
    {
        try {
            $token = $this->getTokenFromRequest($request);
            
            if (!empty($token)) {
                $this->authService->logout($token);
            }

            $response = ApiResponse::success(null, '退出成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '退出失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 获取当前用户信息
     * GET /sys/auth/profile
     * @param Request $request
     * @return Response
     */
    public function profile(Request $request)
    {
        try {
            $token = $this->getTokenFromRequest($request);
            
            if (empty($token)) {
                $response = ApiResponse::error(ErrorCode::TOKEN_MISSING, '未提供访问令牌');
                return new Response(401, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $admin = $this->authService->getAdminByToken($token);

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
            ];

            $response = ApiResponse::success($profile, '获取用户信息成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取用户信息失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 获取当前用户权限列表
     * GET /sys/auth/permissions
     * @param Request $request
     * @return Response
     */
    public function permissions(Request $request)
    {
        try {

            $token = $this->getTokenFromRequest($request);

            if (empty($token)) {
                $response = ApiResponse::error(ErrorCode::TOKEN_MISSING, '未提供访问令牌');
                return new Response(401, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $admin = $this->authService->getAdminByToken($token);
            var_dump($admin->id);
            $permissions = $this->authService->getAdminPermissions($admin->id);
var_dump($permissions);
            $response = ApiResponse::success($permissions, '获取权限列表成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            var_dump('ApiException');
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            var_dump('Exception');
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取权限列表失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 获取当前用户菜单列表
     * GET /sys/auth/menus
     * @param Request $request
     * @return Response
     */
    public function menus(Request $request)
    {
        try {
            $token = $this->getTokenFromRequest($request);
            
            if (empty($token)) {
                $response = ApiResponse::error(ErrorCode::TOKEN_MISSING, '未提供访问令牌');
                return new Response(401, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $admin = $this->authService->getAdminByToken($token);
            $menus = $this->authService->getAdminMenus($admin->id);

            $response = ApiResponse::success($menus, '获取菜单列表成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '获取菜单列表失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
        }
    }

    /**
     * 刷新Token
     * POST /sys/auth/refresh
     * @param Request $request
     * @return Response
     */
    public function refresh(Request $request)
    {
        try {
            $refreshToken = $request->post('refresh_token', '');
            
            if (empty($refreshToken)) {
                $response = ApiResponse::error(ErrorCode::PARAMETER_ERROR, '未提供刷新令牌');
                return new Response(400, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $tokenData = $this->authService->refreshToken($refreshToken);

            $response = ApiResponse::success($tokenData, '刷新令牌成功');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, '刷新令牌失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
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
                $response = ApiResponse::error(ErrorCode::TOKEN_MISSING, '未提供访问令牌');
                return new Response(401, ['Content-Type' => 'application/json'], json_encode($response));
            }

            $payload = $this->authService->validateToken($token);
            $remainingTime = $this->authService->getTokenRemainingTime($token);

            $result = [
                'valid' => true,
                'remaining_time' => $remainingTime,
                'user_id' => $payload['user_id'] ?? 0,
                'expires_at' => $payload['exp'] ?? 0,
            ];

            $response = ApiResponse::success($result, 'Token有效');
            return new Response(200, ['Content-Type' => 'application/json'], json_encode($response));

        } catch (ApiException $e) {
            $response = ApiResponse::error($e->getCode(), $e->getMessage());
            $httpCode = ErrorCode::getHttpCodeByCode($e->getCode());
            return new Response($httpCode, ['Content-Type' => 'application/json'], json_encode($response));
        } catch (\Exception $e) {
            $response = ApiResponse::error(ErrorCode::SYSTEM_ERROR, 'Token验证失败：' . $e->getMessage());
            return new Response(500, ['Content-Type' => 'application/json'], json_encode($response));
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