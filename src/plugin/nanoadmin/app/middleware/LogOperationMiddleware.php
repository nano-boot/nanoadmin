<?php

namespace plugin\nanoadmin\app\middleware;

use plugin\nanoadmin\app\service\LogOperationService;
use plugin\nanoadmin\app\model\ModelFactory;
use Webman\Http\Response;
use Webman\Http\Request;

/**
 * 操作日志中间件
 * 记录所有已认证接口的访问日志
 *
 * exclude_routes 由 BaseMiddleware::resolveExcludeRoutes() 统一解析：
 * - 支持 @no_permission_routes 引用语法（permission 和 log_operation 共享）
 * - 自动注入平台路由 + Swagger 路由
 *
 * Phase 2 行为：
 *  - 未认证请求直接放行（不记录日志，因为没有 admin 信息）
 *  - 已认证请求按 log_operation.exclude_routes 判断
 *  - 完全匿名接口（#[AllowAnonymous(requireToken: false)]）由于未认证走第一条短路
 *  - Phase 3 计划：通过 #[Permission(log: false)] 精细化控制
 */
class LogOperationMiddleware extends BaseMiddleware
{
    /**
     * 不记录日志的 HTTP 方法（从 config 读取）
     * @var array
     */
    protected array $excludeMethods = [];

    /**
     * 请求参数中需要脱敏的字段（从 config 读取）
     * @var array
     */
    protected array $sensitiveKeys = [];

    /**
     * 解析后的配置缓存
     * @var array|null
     */
    protected static ?array $cachedConfig = null;

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * 从配置文件加载默认配置
     */
    protected function loadConfig(): void
    {
        if (self::$cachedConfig === null) {
            $config = function_exists('config') ? config('plugin.nanoadmin.nanoadmin.log_operation', []) : [];
            self::$cachedConfig = is_array($config) ? $config : [];
        }

        // 使用 BaseMiddleware 的 resolveExcludeRoutes 解析路由（含 @ 引用 + 自动注入）
        $this->excludeRoutes  = $this->resolveExcludeRoutes(self::$cachedConfig);
        $this->excludeMethods = array_map('strtoupper', self::$cachedConfig['exclude_methods'] ?? []);
        $this->sensitiveKeys  = array_map('strtolower', self::$cachedConfig['sensitive_keys'] ?? []);
    }

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
        $method = strtoupper($request->method());

        // 方法过滤
        if (in_array($method, $this->excludeMethods)) {
            return true;
        }

        // 路由过滤（使用父类的 matchesExcludeRoute）
        return $this->matchesExcludeRoute($request);
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

            // 获取响应数据（从 JSON body 中解析业务 code 和 msg）
            $responseData = $this->getResponseData($response);

            // 获取 HTTP status
            $httpStatus = $this->getHttpStatus($response);

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
                'response_code' => $responseData['code'],
                'response_msg' => $responseData['msg'],
                'http_status' => $httpStatus,
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
     * 从响应体中解析业务状态码和消息
     * @param Response $response
     * @return array ['code' => int, 'msg' => string]
     */
    protected function getResponseData(Response $response): array
    {
        $code = 20000;
        $msg = '';

        $body = $response->rawBody();
        if ($body) {
            $data = json_decode($body, true);
            if (isset($data['code'])) {
                $code = (int) $data['code'];
            }
            if (isset($data['msg'])) {
                $msg = (string) $data['msg'];
            }
        }

        return ['code' => $code, 'msg' => $msg];
    }

    /**
     * 从响应体中解析业务状态码（兼容方法）
     * @param Response $response
     * @return int
     * @deprecated 使用 getResponseData() 替代
     */
    protected function getResponseCode(Response $response): int
    {
        return $this->getResponseData($response)['code'];
    }

    /**
     * 获取 HTTP 状态码
     * @param Response $response
     * @return int
     */
    protected function getHttpStatus(Response $response): int
    {
        return $response->getStatusCode();
    }

    /**
     * 解析模块名称、操作类型和描述
     *
     * 规则：
     *  - module 取 path 第二段（如 menu / admin / login-log），含 list → 用于中文化
     *  - action 优先取 path 第三段（数字视为 ID，子动作为「详情」），空则为 HTTP 方法映射（更新/创建/列表/删除）
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
        // path 第三段：可能是 {id}（数字）或子操作（roles / sort ...）
        // 这里用作 sub-action hint，HTTP 方法仍主导基础动作
        $subAction = $segments[1] ?? '';

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

        // 解析操作类型：HTTP 方法决定基础动作，path 决定 sub-action（详情 / 子操作）
        $baseActionMap = [
            'GET' => '查询',
            'POST' => '创建',
            'PUT' => '更新',
            'PATCH' => '修改',
            'DELETE' => '删除',
        ];

        $subActionMap = [
            'select' => '下拉列表',
            'stats' => '统计',
            'roles' => '角色',
            'permissions' => '权限',
            'menus' => '菜单',
            'route' => '路由',
            'group' => '分组',
            'download' => '下载',
            'sort' => '排序',
            'upload' => '上传',
            'batch' => '批量',
            'info' => '资料',
            'password' => '密码',
            'export' => '导出',
            'import' => '导入',
        ];

        $baseName = $baseActionMap[$method] ?? '请求';

        // 1) 命名子动作（如 /admin/123/roles → 「分配角色」）
        if ($subAction !== '' && isset($subActionMap[$subAction])) {
            $actionName = ($method === 'POST' && in_array($subAction, ['roles', 'permissions', 'menus'], true))
                ? '分配' . $subActionMap[$subAction]
                : $subActionMap[$subAction] . $baseName;
        }
        // 2) 数字子动作（如 /admin/123、/menu/200）→ 详情类操作
        elseif ($subAction !== '' && is_numeric($subAction)) {
            if ($method === 'GET') {
                $actionName = '详情';
            } else {
                // PUT /admin/123 或 DELETE /admin/123 等 → 仍按基础动作
                $actionName = $baseName;
            }
        }
        // 3) 空 subAction → 仅基础动作（如 PUT /admin）
        else {
            $actionName = $baseName;
        }

        // 生成描述：「菜单」「更新」/「菜单」「详情」「查询」
        $description = "{$moduleName}「{$actionName}」";

        return [
            'module' => $moduleName,
            'action' => $actionName,
            'description' => $description,
        ];
    }

    /**
     * 请求参数单列最大字节数缓存（按列定义动态探测）
     * TEXT 列最大 65535，MEDIUMTEXT 16777215，这里给硬上限 60000 防失控
     * @var int|null
     */
    protected static ?int $requestParamsMaxBytes = null;

    /**
     * 探测 th_sys_log_operation.request_params 列允许的最大字节数
     * - TEXT (utf8mb4): 16383 字符 ≈ 65532 字节
     * - VARCHAR(500): 500 字符 ≈ 2000 字节
     * - 若探测失败，安全起见回落到 500（旧 schema 默认）
     * @return int
     */
    protected function detectRequestParamsMaxBytes(): int
    {
        if (self::$requestParamsMaxBytes !== null) {
            return self::$requestParamsMaxBytes;
        }

        try {
            $sql = "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                      AND COLUMN_NAME = 'request_params'";
            $row = \support\Db::selectOne($sql, ['sys_log_operation']);

            if ($row) {
                // webman 默认 PDO::FETCH_OBJ，但其他模式可能返回数组。这里
                // 两种都兼容：object 用 ->，array 用 []。
                $get = static fn(string $key) => is_array($row)
                    ? ($row[$key] ?? null)
                    : ($row->$key ?? null);

                $type = strtolower((string)($get('DATA_TYPE')));
                $len  = (int)($get('CHARACTER_MAXIMUM_LENGTH') ?? 0);
                if (in_array($type, ['text', 'tinytext', 'mediumtext', 'longtext'], true)) {
                    self::$requestParamsMaxBytes = match ($type) {
                        'tinytext' => 255,
                        'text' => 65535,
                        'mediumtext' => 16777215,
                        'longtext' => 4294967295,
                        default => 65535,
                    };
                    return self::$requestParamsMaxBytes;
                }
                if ($type === 'varchar' || $type === 'char') {
                    self::$requestParamsMaxBytes = max(1, $len);
                    return self::$requestParamsMaxBytes;
                }
            }
        } catch (\Throwable $e) {
            // 表/列还没建好（首次安装），回落
        }

        // 兜底：500 字节 ≈ install.sql 旧 schema 行为
        self::$requestParamsMaxBytes = 500;
        return self::$requestParamsMaxBytes;
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
            foreach ($postParams as $key => $value) {
                if (in_array(strtolower($key), $this->sensitiveKeys, true)) {
                    $postParams[$key] = '******';
                }
            }
            $params['_POST'] = $postParams;
        }

        if (empty($params)) {
            return '';
        }

        $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '';
        }

        $maxBytes = $this->detectRequestParamsMaxBytes();
        if (strlen($json) <= $maxBytes) {
            return $json;
        }

        // 超长截断：保留头尾结构和末尾 "...[truncated]" 标记
        $marker = sprintf('...[truncated %d bytes]', strlen($json) - $maxBytes + 32);
        $keep = max(0, $maxBytes - strlen($marker));
        return mb_strcut($json, 0, $keep) . $marker;
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
