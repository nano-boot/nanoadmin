<?php

namespace plugin\theadmin\app\middleware;

use plugin\theadmin\app\service\LogOperationService;
use plugin\theadmin\app\model\ModelFactory;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 操作日志中间件
 * 记录所有已认证接口的访问日志
 */
class OperationLogMiddleware implements MiddlewareInterface
{
    /**
     * 不记录日志的路由（支持前缀匹配）
     * @var array
     */
    protected array $excludeRoutes = [
        '/sys/auth/login',          // 登录
        '/sys/auth/refresh',        // 刷新Token
        '/sys/auth/check',          // 检查Token
        '/sys/menu/route',         // 动态路由
        '/sys/auth/info',           // 当前用户信息
        '/sys/auth/permissions',    // 权限列表
        '/sys/auth/menus',          // 菜单列表
    ];

    /**
     * 不记录日志的 HTTP 方法
     * @var array
     */
    protected array $excludeMethods = ['OPTIONS'];

    /**
     * 处理请求
     * @param Request $request
     * @param callable $handler
     * @return Response
     */
    public function process(Request $request, callable $handler): Response
    {
        // 仅对已认证的请求记录操作日志
        if (!isset($request->admin)) {
            return $handler($request);
        }

        // 检查是否应该跳过
        if ($this->shouldSkip($request)) {
            return $handler($request);
        }

        // 记录开始时间
        $startTime = microtime(true);

        // 执行实际请求
        $response = $handler($request);

        // 计算耗时
        $costTime = round(microtime(true) - $startTime, 3);

        // 异步记录日志（避免阻塞响应）
        $this->recordAsync($request, $response, $costTime);

        return $response;
    }

    /**
     * 检查是否应该跳过记录
     * @param Request $request
     * @return bool
     */
    protected function shouldSkip(Request $request): bool
    {
        $path = $request->path();
        $method = strtoupper($request->method());

        // 方法过滤
        if (in_array($method, $this->excludeMethods)) {
            return true;
        }

        // 路由过滤
        foreach ($this->excludeRoutes as $route) {
            if (str_starts_with($path, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 异步记录操作日志
     * @param Request $request
     * @param Response $response
     * @param float $costTime
     * @return void
     */
    protected function recordAsync(Request $request, Response $response, float $costTime): void
    {
        try {
            $admin = $request->admin;
            $path = $request->path();
            $method = strtoupper($request->method());

            // 解析模块和操作
            $parsed = $this->parseModuleAndAction($path, $method);

            // 获取请求参数
            $requestParams = $this->getRequestParams($request);

            // 获取响应码（从 JSON body 中解析）
            $responseCode = $this->getResponseCode($response);

            // 构建日志数据
            $logData = [
                'admin_id' => $admin->id,
                'username' => $admin->username,
                'module' => $parsed['module'],
                'action' => $parsed['action'],
                'description' => $parsed['description'],
                'request_method' => $method,
                'request_url' => $path,
                'request_params' => $requestParams,
                'response_code' => $responseCode,
                'cost_time' => $costTime,
                'ip' => $request->getRealIp() ?? '',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            // 使用异步任务记录日志
            $this->saveLog($logData);
        } catch (\Exception $e) {
            error_log('OperationLog Error: ' . $e->getMessage());
        }
    }

    /**
     * 从响应体中解析业务状态码
     * @param Response $response
     * @return int
     */
    protected function getResponseCode(Response $response): int
    {
        $body = $response->rawBody();
        if ($body) {
            $data = json_decode($body, true);
            if (isset($data['code'])) {
                return (int) $data['code'];
            }
        }
        return 200;
    }

    /**
     * 解析模块名称、操作类型和描述
     * @param string $path
     * @param string $method
     * @return array
     */
    protected function parseModuleAndAction(string $path, string $method): array
    {
        // 路径示例: sys/admin -> admin, sys/admin/1 -> admin, sys/admin/1/roles -> admin
        $segments = explode('/', trim($path, '/'));

        // 跳过 'sys' 前缀
        $segments = array_slice($segments, 1);

        $module = $segments[0] ?? 'unknown';
        $action = $segments[1] ?? '';

        // 规范化模块名称
        $moduleMap = [
            'admin' => '管理员',
            'role' => '角色',
            'menu' => '菜单',
            'permission' => '权限',
            'config' => '配置',
            'dict-type' => '字典类型',
            'dict-data' => '字典数据',
            'file' => '文件',
            'files' => '文件',
            'login-log' => '登录日志',
            'operation-log' => '操作日志',
        ];

        $moduleName = $moduleMap[$module] ?? $module;

        // 解析操作类型
        $actionMap = [
            'GET' => [
                '' => '列表',
                'select' => '下拉列表',
                'stats' => '统计',
                'roles' => '角色列表',
                'permissions' => '权限列表',
                'menus' => '菜单列表',
                'route' => '路由',
                'group' => '分组',
                'download' => '下载',
            ],
            'POST' => [
                '' => '创建',
                'roles' => '分配角色',
                'permissions' => '分配权限',
                'menus' => '分配菜单',
                'sort' => '排序',
                'upload' => '上传',
                'batch' => '批量操作',
            ],
            'PUT' => [
                '' => '更新',
                'info' => '更新资料',
                'password' => '修改密码',
                'batch' => '批量更新',
            ],
            'DELETE' => [
                '' => '删除',
                'batch' => '批量删除',
            ],
        ];

        $actionName = $action;
        if (isset($actionMap[$method][$action])) {
            $actionName = $actionMap[$method][$action];
        } elseif (is_numeric($action)) {
            $actionName = '详情';
        }

        // 生成描述
        $description = sprintf(
            '%s%s%s',
            $moduleName,
            $actionName ? "「{$actionName}」" : '',
            $this->getMethodName($method)
        );

        return [
            'module' => $moduleName,
            'action' => $actionName ?: $method,
            'description' => $description,
        ];
    }

    /**
     * 获取请求参数
     * @param Request $request
     * @return string
     */
    protected function getRequestParams(Request $request): string
    {
        $params = [];

        // GET 参数
        $getParams = $request->get();
        if (!empty($getParams)) {
            $params['_GET'] = $getParams;
        }

        // POST 参数（排除敏感字段）
        $postParams = $request->post();
        if (!empty($postParams)) {
            $sensitiveKeys = ['password', 'password_confirm', 'old_password', 'new_password', 'token', 'secret'];
            foreach ($postParams as $key => $value) {
                if (in_array(strtolower($key), $sensitiveKeys)) {
                    $postParams[$key] = '******';
                }
            }
            $params['_POST'] = $postParams;
        }

        if (empty($params)) {
            return '';
        }

        $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return mb_substr($json, 0, 65535);
    }

    /**
     * 获取 HTTP 方法中文名称
     * @param string $method
     * @return string
     */
    protected function getMethodName(string $method): string
    {
        return match ($method) {
            'GET' => '查询',
            'POST' => '操作',
            'PUT' => '更新',
            'DELETE' => '删除',
            'PATCH' => '修改',
            default => '请求',
        };
    }

    /**
     * 保存日志
     * @param array $logData
     * @return void
     */
    protected function saveLog(array $logData): void
    {
        try {
            $operationLogService = new LogOperationService(ModelFactory::log_operation());
            $operationLogService->recordOperation($logData);
        } catch (\Exception $e) {
            error_log('OperationLog Save Error: ' . $e->getMessage());
        }
    }

    /**
     * 添加排除路由
     * @param string|array $routes
     * @return void
     */
    public function addExcludeRoutes(string|array $routes): void
    {
        if (is_string($routes)) {
            $routes = [$routes];
        }
        $this->excludeRoutes = array_merge($this->excludeRoutes, $routes);
    }

    /**
     * 添加排除的 HTTP 方法
     * @param string|array $methods
     * @return void
     */
    public function addExcludeMethods(string|array $methods): void
    {
        if (is_string($methods)) {
            $methods = [$methods];
        }
        $this->excludeMethods = array_merge($this->excludeMethods, array_map('strtoupper', $methods));
    }
}
