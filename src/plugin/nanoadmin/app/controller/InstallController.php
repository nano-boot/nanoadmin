<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\controller;

use plugin\nanoadmin\app\service\InstallService;
use support\Request;
use support\Response;
use Throwable;

/**
 * 安装向导控制器
 *
 * - GET  /install  → 渲染向导 HTML
 * - POST /install  → 执行安装
 *
 * 向导 HTML 和静态资源通过 CDN 内联，零构建依赖。
 *
 * InstallGuard 已对 /install 路径放行；已安装时直接返回"已安装"页。
 */
class InstallController
{
    private InstallService $service;

    public function __construct()
    {
        $this->service = new InstallService();
    }

    /**
     * 渲染向导页面
     */
    public function index(Request $request): Response
    {
        $data = [
            'app'          => 'nanoadmin',
            'version'      => $this->getVersion(),
            'frontend_url' => $this->getFrontendUrl(),
            'installed'    => $this->service->isInstalled(),
            'env'          => $this->service->checkEnv(),
        ];

        return view('install/index', $data)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * AJAX: 检测环境
     */
    public function checkEnv(Request $request): Response
    {
        return json($this->service->checkEnv());
    }

    /**
     * AJAX: 测试数据库连接
     */
    public function testDatabase(Request $request): Response
    {
        $db = $this->extractDbParams($request);
        $result = $this->service->testDatabaseConnection($db);

        return json([
            'code'    => $result['success'] ? 20000 : 40000,
            'message' => $result['message'],
            'data'    => $result,
        ]);
    }

    /**
     * AJAX: 测试 Redis 连接
     *
     * 只需要 host / port / password 三个字段，database 不影响连通性
     */
    public function testRedis(Request $request): Response
    {
        $redis = [
            'host'     => trim((string) $request->post('redis_host', '')),
            'port'     => (int) $request->post('redis_port', 6379),
            'password' => (string) $request->post('redis_password', ''),
            'database' => (int) $request->post('redis_database', 0),
        ];

        if ($redis['host'] === '') {
            return json([
                'code'    => 40000,
                'message' => '请填写 Redis 主机',
                'data'    => ['success' => false],
            ]);
        }

        $result = $this->service->testRedisConnection($redis);

        return json([
            'code'    => $result['success'] ? 20000 : 40000,
            'message' => $result['message'],
            'data'    => $result,
        ]);
    }

    /**
     * AJAX: 执行安装
     */
    public function run(Request $request): Response
    {
        try {
            if ($this->service->isInstalled()) {
                return json([
                    'code'    => 40900,
                    'message' => '系统已安装，无需重复安装',
                    'data'    => null,
                ]);
            }

            $params = $this->extractInstallParams($request);
            $result = $this->service->runInstallation($params);

            return json([
                'code'    => 20000,
                'message' => $result['message'],
                'data'    => [
                    'success' => true,
                    'admin'   => $result['admin'] ?? null,
                ],
            ]);
        } catch (Throwable $e) {
            return json([
                'code'    => 50000,
                'message' => $e->getMessage(),
                'data'    => null,
            ]);
        }
    }

    /**
     * 提取数据库参数
     */
    private function extractDbParams(Request $request): array
    {
        $post = $request->post();
        return [
            'host'     => trim((string) ($post['host'] ?? '127.0.0.1')),
            'port'     => (string) (int) ($post['port'] ?? 3306),
            'name'     => trim((string) ($post['name'] ?? 'nanoadmin')),
            'user'     => trim((string) ($post['user'] ?? 'root')),
            'password' => (string) ($post['password'] ?? ''),
            // 默认与 plugin/nanoadmin/sql/install.sql 里的硬编码前缀保持一致，避免无前缀时空替换
            'prefix'   => $this->normalizePrefix((string) ($post['prefix'] ?? 'na_')),
        ];
    }

    /**
     * 归一化表前缀：仅允许字母数字下划线；为空时回落到 na_
     */
    private function normalizePrefix(string $raw): string
    {
        $cleaned = preg_replace('/[^a-zA-Z0-9_]/', '', $raw) ?? '';
        return $cleaned === '' ? 'na_' : $cleaned;
    }

    /**
     * 提取完整安装参数
     */
    private function extractInstallParams(Request $request): array
    {
        $redis = $this->extractRedisParams($request);

        return $this->extractDbParams($request) + [
            'admin_user'             => trim((string) $request->post('admin_user', 'admin')),
            'admin_password'         => (string) $request->post('admin_password', ''),
            'admin_password_confirm' => (string) $request->post('admin_password_confirm', ''),
            'admin_nickname'         => trim((string) $request->post('admin_nickname', '超级管理员')),
            'redis'                  => $redis,
        ];
    }

    /**
     * 提取 Redis 配置参数。
     *
     * 仅当 host 非空时才视为填写了 Redis 配置；未填写返回空数组，
     * 由 InstallService 据此跳过 .env 中 REDIS_* 的写入，但仍会生成 env() 形式的 config/redis.php。
     *
     * @return array{host:string,port:int,password:string,database:int}|array{}
     */
    private function extractRedisParams(Request $request): array
    {
        $host = trim((string) $request->post('redis_host', ''));
        if ($host === '') {
            return [];
        }

        $port = (int) $request->post('redis_port', 6379);
        if ($port < 1 || $port > 65535) {
            $port = 6379;
        }

        $database = (int) $request->post('redis_database', 0);
        if ($database < 0) {
            $database = 0;
        }

        return [
            'host'     => $host,
            'port'     => $port,
            'password' => (string) $request->post('redis_password', ''),
            'database' => $database,
        ];
    }

    /**
     * 获取插件版本号
     */
    private function getVersion(): string
    {
        $composerFile = base_path() . '/plugin/nanoadmin/composer.json';
        if (is_file($composerFile)) {
            $content = file_get_contents($composerFile);
            if ($content && preg_match('/"version"\s*:\s*"([^"]+)"/', $content, $m)) {
                return $m[1];
            }
        }
        return '1.0.0';
    }

    /**
     * 获取前端地址（用于安装完成跳转）
     */
    private function getFrontendUrl(): string
    {
        return (string) (config('plugin.nanoadmin.frontend_url') ?? 'http://localhost:3006');
    }
}
