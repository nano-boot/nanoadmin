<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use plugin\theadmin\app\service\InstallService;
use support\Request;
use support\Response;

/**
 * 安装向导控制器
 * 提供可视化安装向导的 API 接口
 */
class InstallController
{
    protected InstallService $installService;

    public function __construct()
    {
        $this->installService = new InstallService();
    }

    /**
     * 获取安装状态
     * GET /sys/install/status
     */
    public function status(Request $request): Response
    {
        $data = [
            'installed' => $this->installService->isInstalled(),
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'required_extensions' => $this->installService->getRequiredExtensions(),
            'check_result' => $this->installService->checkEnvironment(),
        ];

        return R::success($data);
    }

    /**
     * 环境检测
     * GET /sys/install/environment
     */
    public function environment(Request $request): Response
    {
        $checks = [
            'php_version' => [
                'name' => 'PHP 版本',
                'required' => '8.1.0',
                'current' => PHP_VERSION,
                'passed' => version_compare(PHP_VERSION, '8.1.0', '>='),
            ],
            'pdo' => [
                'name' => 'PDO 扩展',
                'required' => true,
                'current' => extension_loaded('pdo'),
                'passed' => extension_loaded('pdo'),
            ],
            'pdo_mysql' => [
                'name' => 'PDO MySQL 扩展',
                'required' => true,
                'current' => extension_loaded('pdo_mysql'),
                'passed' => extension_loaded('pdo_mysql'),
            ],
            'mbstring' => [
                'name' => 'MBString 扩展',
                'required' => true,
                'current' => extension_loaded('mbstring'),
                'passed' => extension_loaded('mbstring'),
            ],
            'openssl' => [
                'name' => 'OpenSSL 扩展',
                'required' => true,
                'current' => extension_loaded('openssl'),
                'passed' => extension_loaded('openssl'),
            ],
            'json' => [
                'name' => 'JSON 扩展',
                'required' => true,
                'current' => extension_loaded('json'),
                'passed' => extension_loaded('json'),
            ],
            'fileinfo' => [
                'name' => 'Fileinfo 扩展',
                'required' => true,
                'current' => extension_loaded('fileinfo'),
                'passed' => extension_loaded('fileinfo'),
            ],
            'dir_writable' => [
                'name' => '目录写入权限',
                'required' => true,
                'current' => is_writable(base_path()),
                'passed' => is_writable(base_path()),
            ],
        ];

        $allPassed = true;
        foreach ($checks as $check) {
            if (!$check['passed']) {
                $allPassed = false;
            }
        }

        return R::success([
            'checks' => $checks,
            'all_passed' => $allPassed,
        ]);
    }

    /**
     * 测试数据库连接
     * POST /sys/install/test-connection
     */
    public function testConnection(Request $request): Response
    {
        $params = $request->all();

        $result = $this->installService->testDatabaseConnection([
            'hostname' => $params['hostname'] ?? '127.0.0.1',
            'hostport' => $params['hostport'] ?? '3306',
            'database' => $params['database'] ?? '',
            'username' => $params['username'] ?? 'root',
            'password' => $params['password'] ?? '',
            'charset' => $params['charset'] ?? 'utf8mb4',
            'prefix' => $params['prefix'] ?? 'th_',
        ]);

        if ($result['success']) {
            return R::success($result, '数据库连接成功');
        }

        return R::error($result['message']);
    }

    /**
     * 执行安装
     * POST /sys/install/execute
     */
    public function execute(Request $request): Response
    {
        $params = $request->all();

        // 验证必填参数
        $required = ['hostname', 'database', 'username', 'admin_username', 'admin_password'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return R::error("参数 {$field} 不能为空");
            }
        }

        // 验证管理员密码强度
        if (strlen($params['admin_password']) < 6) {
            return R::error('管理员密码至少 6 个字符');
        }

        try {
            $result = $this->installService->install([
                // 数据库配置
                'hostname' => $params['hostname'] ?? '127.0.0.1',
                'hostport' => $params['hostport'] ?? '3306',
                'database' => $params['database'] ?? '',
                'username' => $params['username'] ?? 'root',
                'password' => $params['password'] ?? '',
                'charset' => $params['charset'] ?? 'utf8mb4',
                'prefix' => $params['prefix'] ?? 'th_',

                // 管理员配置
                'admin_username' => $params['admin_username'],
                'admin_password' => $params['admin_password'],
                'admin_nickname' => $params['admin_nickname'] ?? '管理员',

                // 安装选项
                'create_database' => $params['create_database'] ?? true,
                'import_sample_data' => $params['import_sample_data'] ?? false,
            ]);

            if ($result['success']) {
                return R::success([
                    'admin_username' => $params['admin_username'],
                ], '安装成功');
            }

            return R::error($result['message']);

        } catch (\Exception $e) {
            return R::error('安装失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取安装进度
     * GET /sys/install/progress
     */
    public function progress(Request $request): Response
    {
        $progress = $this->installService->getProgress();

        return R::success($progress);
    }
}
