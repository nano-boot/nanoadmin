<?php

namespace plugin\nanoadmin\app\controller;

/**
 * 安装向导控制器
 * 处理安装向导的 API 请求
 * 
 * @author TheAdmin Team
 */
class InstallApiController
{
    protected \InstallModel $model;
    protected \YxEnv $env;

    public function __construct()
    {
        // 动态加载安装所需的文件
        require_once public_path() . '/install/model.php';
        require_once public_path() . '/install/env.php';
        
        $this->model = new \InstallModel();
        $this->env = new \YxEnv();
        
        // 加载现有环境变量
        $envFile = $this->model->getAppRoot() . '/.env';
        if (file_exists($envFile)) {
            $this->env->load($envFile);
        }
    }

    /**
     * 获取安装状态
     */
    public function status(): array
    {
        $installed = $this->model->appIsInstalled();

        return [
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'installed' => $installed,
                'step' => $installed ? 'complete' : 'pending',
            ]
        ];
    }

    /**
     * 获取环境信息
     */
    public function getEnvInfo(): array
    {
        $serverInfo = $this->model->getServerInfo();

        return [
            'code' => 200,
            'msg' => 'success',
            'data' => $serverInfo
        ];
    }

    /**
     * 环境检测
     */
    public function checkEnv(): array
    {
        $checks = [
            'php' => [
                'name' => 'PHP版本',
                'require' => '>= 8.1',
                'current' => PHP_VERSION,
                'status' => $this->model->checkPHP()
            ],
            'pdo' => [
                'name' => 'PDO扩展',
                'require' => '必须',
                'status' => $this->model->checkPDO()
            ],
            'pdo_mysql' => [
                'name' => 'PDO MySQL扩展',
                'require' => '必须',
                'status' => $this->model->checkPDOMySQL()
            ],
            'json' => [
                'name' => 'JSON扩展',
                'require' => '必须',
                'status' => $this->model->checkJSON()
            ],
            'openssl' => [
                'name' => 'OpenSSL扩展',
                'require' => '必须',
                'status' => $this->model->checkOpenssl()
            ],
            'mbstring' => [
                'name' => 'Mbstring扩展',
                'require' => '必须',
                'status' => $this->model->checkMbstring()
            ],
            'gd' => [
                'name' => 'GD扩展',
                'require' => '建议',
                'status' => $this->model->checkGd()
            ],
            'curl' => [
                'name' => 'CURL扩展',
                'require' => '建议',
                'status' => $this->model->checkCurl()
            ],
        ];

        // 目录权限检测
        $dirs = [
            'runtime' => 'runtime目录',
            'config' => 'config目录',
            'plugin' => 'plugin目录',
        ];

        $dirChecks = [];
        foreach ($dirs as $key => $name) {
            $dirChecks[$key] = [
                'name' => $name,
                'status' => $this->model->checkDirWrite($key),
            ];
        }

        // 检查是否通过
        $passed = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $passed = false;
                break;
            }
        }

        foreach ($dirChecks as $check) {
            if ($check['status'] === 'fail') {
                $passed = false;
                break;
            }
        }

        return [
            'code' => 200,
            'msg' => 'success',
            'data' => [
                'passed' => $passed,
                'extensions' => $checks,
                'directories' => $dirChecks,
            ]
        ];
    }

    /**
     * 测试数据库连接（仅测试连接，不创建任何资源）
     */
    public function checkDatabase(array $post): array
    {
        $required = ['host', 'port', 'user', 'name', 'prefix'];
        foreach ($required as $field) {
            if (empty($post[$field])) {
                return [
                    'code' => 400,
                    'msg' => '请填写完整的数据库信息',
                    'data' => null
                ];
            }
        }

        $config = [
            'host' => $post['host'],
            'port' => $post['port'],
            'user' => $post['user'],
            'password' => $post['password'] ?? '',
            'name' => $post['name'],
            'prefix' => $post['prefix'],
        ];

        // 仅测试连接，不创建数据库和表
        $result = $this->model->testConnection($config);

        if ($result->result === 'ok') {
            return [
                'code' => 200,
                'msg' => '数据库连接成功222',
                'data' => [
                    'success' => true,
                ]
            ];
        } else {
            return [
                'code' => 400,
                'msg' => $result->error ?? '数据库连接失败',
                'data' => null
            ];
        }
    }

    /**
     * 执行安装
     */
    public function doInstall(array $post): array
    {
        if (empty($post['admin_user']) || empty($post['admin_password'])) {
            return [
                'code' => 400,
                'msg' => '请填写管理员账号和密码',
                'data' => null
            ];
        }

        if (strlen($post['admin_password']) < 6) {
            return [
                'code' => 400,
                'msg' => '管理员密码长度不能少于6位',
                'data' => null
            ];
        }

        if ($post['admin_password'] !== ($post['admin_password_confirm'] ?? '')) {
            return [
                'code' => 400,
                'msg' => '两次密码不一致',
                'data' => null
            ];
        }

        $config = [
            'host' => $post['host'] ?? '127.0.0.1',
            'port' => $post['port'] ?? '3306',
            'name' => $post['name'] ?? 'nanoadmin',
            'user' => $post['user'] ?? 'root',
            'password' => $post['password'] ?? '',
            'prefix' => $post['prefix'] ?? 'th_',
            'admin_user' => $post['admin_user'],
            'admin_password' => $post['admin_password'],
            'admin_nickname' => $post['admin_nickname'] ?? '超级管理员',
        ];

        try {
            $dbResult = $this->model->checkConfig($config['name'], $config);
            if ($dbResult->result !== 'ok') {
                return [
                    'code' => 400,
                    'msg' => $dbResult->error ?? '数据库配置失败',
                    'data' => null
                ];
            }

            if (!$this->model->initAdminAccount($config)) {
                return [
                    'code' => 500,
                    'msg' => '安装失败: ' . ($this->model->getLastError() ?: '初始化管理员数据失败'),
                    'data' => null
                ];
            }

            $envFile = $this->model->getAppRoot() . '/.env';
            $this->env->makeEnv($envFile);
            $this->env->putEnv($envFile, $config);

            $this->model->mkLockFile();

            return [
                'code' => 200,
                'msg' => '安装成功',
                'data' => [
                    'success' => true,
                    'admin' => [
                        'username' => $post['admin_user'],
                        'password' => $post['admin_password'],
                    ],
                ]
            ];

        } catch (\Exception $e) {
            return [
                'code' => 500,
                'msg' => '安装失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * 卸载（重新安装）
     */
    public function uninstall(): array
    {
        try {
            $this->model->rmLockFile();

            return [
                'code' => 200,
                'msg' => '卸载成功，可以重新安装',
                'data' => null
            ];
        } catch (\Exception $e) {
            return [
                'code' => 500,
                'msg' => '卸载失败: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
