<?php

namespace plugin\nanoadmin\app\middleware;

use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\common\IpLocation;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use plugin\nanoadmin\app\common\JwtUtil;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\library\swagger\SwaggerRoutes;
use plugin\nanoadmin\app\model\ModelFactory;
use plugin\nanoadmin\app\service\LogLoginService;

/**
 * 认证中间件
 * 处理JWT Token验证和用户信息注入
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * 不需要认证的路由（前缀匹配，从 config('plugin.nanoadmin.nanoadmin.auth.exclude_routes') 读取）
     * @var array
     */
    protected array $excludeRoutes = [];

    /**
     * 登录失败是否记录到登录日志
     * @var bool
     */
    protected bool $recordFailedLogin = true;

    /**
     * 解析后的配置缓存（避免每次请求重复解析）
     * @var array|null
     */
    protected static ?array $cachedConfig = null;

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * 从配置文件加载默认排除路由等设置
     */
    protected function loadConfig(): void
    {
        if (self::$cachedConfig === null) {
            $config = function_exists('config') ? config('plugin.nanoadmin.nanoadmin.auth', []) : [];
            self::$cachedConfig = is_array($config) ? $config : [];
        }

        $this->excludeRoutes     = self::$cachedConfig['exclude_routes'] ?? [];
        // 自动注入 swagger / openapi 路由白名单（随 swagger.php ui_route / doc_route / enabled 同步）
        $this->excludeRoutes     = array_values(array_unique(array_merge($this->excludeRoutes, SwaggerRoutes::excludeRoutes())));
        $this->recordFailedLogin = (bool) (self::$cachedConfig['record_failed_login'] ?? true);
    }

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
                throw new ApiException(Code::UNAUTHORIZED, '缺少认证Token');
            }

            // 验证Token
            $payload = JwtUtil::verifyToken($token);
            // 检查Token类型
            if (!JwtUtil::isAccessToken($payload)) {
                throw new ApiException(Code::TOKEN_INVALID, '无效的Token类型');
            }

            // 获取用户信息
            $userId = $payload['user_id'] ?? null;
            if (empty($userId)) {
                throw new ApiException(Code::TOKEN_INVALID, 'Token中缺少用户信息');
            }
            // 查询用户
            $adminModel = ModelFactory::admin();
            $admin = $adminModel->where('id', $userId)
                                ->with('roles')
                                ->where('status', 1)
                                ->first();

            if (!$admin) {
                throw new ApiException(Code::ACCOUNT_NOT_FOUND, '用户不存在或已被禁用');
            }

            // 将用户信息注入到请求中
            $request->admin = $admin;
            $request->adminId = $admin->id;
            $request->tokenPayload = $payload;

            // 继续处理请求
            return $handler($request);

        } catch (ApiException $e) {
            if ($this->recordFailedLogin) {
                $this->recordFailedLogin($request, $e->getMessage());
            }
            return $this->unauthorizedResponse($e->getMessage(), $e->getErrorCode());
        } catch (\Exception $e) {
            if ($this->recordFailedLogin) {
                $this->recordFailedLogin($request, '认证失败');
            }
            return $this->unauthorizedResponse('认证失败', Code::UNAUTHORIZED->value);
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
        return R::error($message, $code);
    }

    /**
     * 记录认证失败的登录尝试（伪异步：直接写入 + 异常捕获）
     *
     * @param Request $request
     * @param string $reason
     * @return void
     */
    protected function recordFailedLogin(Request $request, string $reason): void
    {
        try {
            $ip = $request->getRealIp() ?? '';
            $ipLocation = new IpLocation();
            $location = $ipLocation->get($ip);

            $loginLogService = new LogLoginService(ModelFactory::log_login());
            $loginLogService->recordLogin([
                'admin_id' => 0,
                'username' => '',
                'ip' => $ip,
                'user_agent' => mb_substr($request->header('User-Agent', ''), 0, 500),
                'location' => $location,
                'status' => 0,
                'login_info' => $reason,
                'login_time' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            error_log('LoginLog Error: ' . $e->getMessage());
        }
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