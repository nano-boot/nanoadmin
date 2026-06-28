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
            'prefix'   => trim((string) ($post['prefix'] ?? 'na_')),
        ];
    }

    /**
     * 提取完整安装参数
     */
    private function extractInstallParams(Request $request): array
    {
        return $this->extractDbParams($request) + [
            'admin_user'             => trim((string) $request->post('admin_user', 'admin')),
            'admin_password'         => (string) $request->post('admin_password', ''),
            'admin_password_confirm' => (string) $request->post('admin_password_confirm', ''),
            'admin_nickname'         => trim((string) $request->post('admin_nickname', '超级管理员')),
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
