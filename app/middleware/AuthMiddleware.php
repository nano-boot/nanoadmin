<?php

namespace plugin\theadmin\app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use plugin\theadmin\app\common\JwtUtil;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\ApiResponse;
use plugin\theadmin\app\common\ErrorCode;
use plugin\theadmin\app\model\ModelFactory;

/**
 * 认证中间件
 * 处理JWT Token验证和用户信息注入
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * 不需要认证的路由
     * @var array
     */
    protected array $excludeRoutes = [
        '/sys/auth/login',
        '/sys/auth/refresh',
        '/sys/install',
    ];

    /**
     * 处理请求
     * @param Request $request
     * @param callable $handler
     * @return Response
     */
    public function process(Request $request, callable $handler): Response
    {
        try {
            // 检查是否需要认证
            if ($this->shouldSkipAuth($request)) {
                return $handler($request);
            }

            // 获取Token
            $token = $this->extractToken($request);
            if (empty($token)) {
                throw new ApiException(ErrorCode::UNAUTHORIZED, '缺少认证Token');
            }

            // 验证Token
            $payload = JwtUtil::verifyToken($token);
            
            // 检查Token类型
            if (!JwtUtil::isAccessToken($payload)) {
                throw new ApiException(ErrorCode::TOKEN_INVALID, '无效的Token类型');
            }

            // 获取用户信息
            $userId = $payload['user_id'] ?? null;
            if (empty($userId)) {
                throw new ApiException(ErrorCode::TOKEN_INVALID, 'Token中缺少用户信息');
            }

            // 查询用户
            $adminModel = ModelFactory::admin();
            $admin = $adminModel->where('id', $userId)
                              ->where('status', 1)
                              ->where('deleted', 0)
                              ->first();

            if (!$admin) {
                throw new ApiException(ErrorCode::ACCOUNT_NOT_FOUND, '用户不存在或已被禁用');
            }

            // 将用户信息注入到请求中
            $request->admin = $admin;
            $request->adminId = $admin->id;
            $request->tokenPayload = $payload;

            // 继续处理请求
            return $handler($request);

        } catch (ApiException $e) {
            return $this->unauthorizedResponse($e->getMessage(), $e->getErrorCode());
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('认证失败', ErrorCode::UNAUTHORIZED->value);
        }
    }

    /**
     * 检查是否应该跳过认证
     * @param Request $request
     * @return bool
     */
    protected function shouldSkipAuth(Request $request): bool
    {
        $path = $request->path();
        
        foreach ($this->excludeRoutes as $route) {
            if (str_starts_with($path, $route)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 从请求中提取Token
     * @param Request $request
     * @return string|null
     */
    protected function extractToken(Request $request): ?string
    {
        // 1. 从Authorization头部获取
        $authHeader = $request->header('Authorization', '');
        if (!empty($authHeader)) {
            return JwtUtil::extractTokenFromHeader($authHeader);
        }

        // 2. 从查询参数获取（兼容性）
        $token = $request->get('token', '');
        if (!empty($token)) {
            return $token;
        }

        // 3. 从POST参数获取（兼容性）
        $token = $request->post('token', '');
        if (!empty($token)) {
            return $token;
        }

        return null;
    }

    /**
     * 返回未授权响应
     * @param string $message
     * @param int $code
     * @return Response
     */
    protected function unauthorizedResponse(string $message, int $code): Response
    {
        $response = ApiResponse::error($code, $message);
        return new Response(401, ['Content-Type' => 'application/json'], json_encode($response));
    }

    /**
     * 添加排除路由
     * @param string|array $routes
     */
    public function addExcludeRoutes(string|array $routes): void
    {
        if (is_string($routes)) {
            $routes = [$routes];
        }
        
        $this->excludeRoutes = array_merge($this->excludeRoutes, $routes);
    }

    /**
     * 设置排除路由
     * @param array $routes
     */
    public function setExcludeRoutes(array $routes): void
    {
        $this->excludeRoutes = $routes;
    }

    /**
     * 获取排除路由列表
     * @return array
     */
    public function getExcludeRoutes(): array
    {
        return $this->excludeRoutes;
    }
}