<?php

namespace plugin\theadmin\app\service;

use support\Db;
use support\Cache;
use Throwable;

/**
 * 安装服务类
 * 处理可视化安装向导的核心逻辑
 */
class InstallService
{
    /**
     * 检查是否已安装
     */
    public function isInstalled(): bool
    {
        try {
            // 检查配置文件是否存在
            $configFile = $this->getConfigFile();
            if (!file_exists($configFile)) {
                return false;
            }

            // 检查数据库连接配置
            $config = require $configFile;
            if (empty($config['database']['connections']['mysql']['database'])) {
                return false;
            }

            // 尝试连接数据库检查表是否存在
            $pdo = $this->createPdoConnection($config['database']['connections']['mysql']);
            $tableExists = $pdo->query("SHOW TABLES LIKE 'th_sys_admin'");
            $result = $tableExists->fetch(\PDO::FETCH_ASSOC);

            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取必需扩展列表
     */
    public function getRequiredExtensions(): array
    {
        return [
            'pdo',
            'pdo_mysql',
            'mbstring',
            'openssl',
            'json',
            'fileinfo',
        ];
    }

    /**
     * 检测环境
     */
    public function checkEnvironment(): array
    {
        $checks = [];
        $allPassed = true;

        // PHP 版本
        $phpPassed = version_compare(PHP_VERSION, '8.1.0', '>=');
        $checks['php_version'] = [
            'name' => 'PHP 版本',
            'required' => '8.1.0',
            'current' => PHP_VERSION,
            'passed' => $phpPassed,
        ];
        if (!$phpPassed) $allPassed = false;

        // 必需扩展
        foreach ($this->getRequiredExtensions() as $ext) {
            $extPassed = extension_loaded($ext);
            $checks['ext_' . $ext] = [
                'name' => ucfirst($ext) . ' 扩展',
                'required' => true,
                'current' => extension_loaded($ext),
                'passed' => $extPassed,
            ];
            if (!$extPassed) $allPassed = false;
        }

        // 目录权限
        $dirs = [
            'base_path' => base_path(),
            'runtime_path' => runtime_path(),
            'public_path' => public_path(),
        ];

        foreach ($dirs as $key => $dir) {
            $dirPassed = is_writable($dir);
            $checks['dir_' . $key] = [
                'name' => '目录写入权限 (' . basename($dir) . ')',
                'required' => true,
                'current' => is_writable($dir),
                'passed' => $dirPassed,
            ];
            if (!$dirPassed) $allPassed = false;
        }

        return [
            'passed' => $allPassed,
            'checks' => $checks,
        ];
    }

    /**
     * 测试数据库连接
     */
    public function testDatabaseConnection(array $config): array
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $config['hostname'] ?? '127.0.0.1',
                $config['hostport'] ?? 3306,
                $config['charset'] ?? 'utf8mb4'
            );

            if (!empty($config['database'])) {
                $dsn .= ';dbname=' . $config['database'];
            }

            $pdo = new \PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // 测试数据库是否存在
            if (empty($config['database'])) {
                return [
                    'success' => true,
                    'message' => '连接成功，请选择或创建数据库',
                    'databases' => $this->getDatabases($pdo),
                ];
            }

            // 测试查询
            $pdo->query("SELECT 1");

            return [
                'success' => true,
                'message' => '数据库连接成功',
            ];

        } catch (\PDOException $e) {
            return [
                'success' => false,
                'message' => '连接失败: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 获取数据库列表
     */
    protected function getDatabases(\PDO $pdo): array
    {
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // 过滤系统数据库
            if (!in_array($row['Database'], ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
                $databases[] = $row['Database'];
            }
        }
        return $databases;
    }

    /**
     * 执行安装
     */
    public function install(array $params): array
    {
        try {
            // 1. 保存数据库配置
            $this->saveDatabaseConfig($params);

            // 2. 创建数据库（如果需要）
            if ($params['create_database'] ?? false) {
                $this->createDatabase($params);
            }

            // 3. 执行 SQL 安装脚本
            $this->executeSql($params);

            // 4. 创建管理员
            $this->createAdmin($params);

            // 5. 标记安装完成
            $this->markAsInstalled($params);

            return [
                'success' => true,
                'message' => '安装成功',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 保存数据库配置
     */
    protected function saveDatabaseConfig(array $params): void
    {
        $configContent = <<<PHP
<?php
/**
 * TheAdmin 数据库配置文件
 * 由安装向导自动生成
 */

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => '{$params['hostname']}',
            'hostport' => '{$params['hostport']}',
            'database' => '{$params['database']}',
            'username' => '{$params['username']}',
            'password' => '{$params['password']}',
            'charset' => '{$params['charset']}',
            'prefix' => '{$params['prefix']}',
            'deploy' => 0,
            'rw_separate' => false,
            'master_num' => 1,
            'slave_no' => '',
            'fields_strict' => true,
            'break_reconnect' => false,
            'trigger_sql' => true,
            'fields_cache' => false,
        ],
    ],
];
PHP;

        $configFile = $this->getConfigFile();
        $dir = dirname($configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($configFile, $configContent);
    }

    /**
     * 创建数据库
     */
    protected function createDatabase(array $params): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $params['hostname'],
                $params['hostport'] ?? 3306,
                $params['charset'] ?? 'utf8mb4'
            );

            $pdo = new \PDO(
                $dsn,
                $params['username'] ?? 'root',
                $params['password'] ?? ''
            );

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$params['database']}`
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        } catch (\PDOException $e) {
            throw new \Exception('创建数据库失败: ' . $e->getMessage());
        }
    }

    /**
     * 执行 SQL 安装脚本
     */
    protected function executeSql(array $params): void
    {
        $sqlFile = plugin_theadmin_path('sql/install.sql');

        if (!file_exists($sqlFile)) {
            throw new \Exception('安装 SQL 文件不存在');
        }

        $sql = file_get_contents($sqlFile);

        // 替换表前缀
        $sql = str_replace('th_', $params['prefix'], $sql);

        // 连接数据库
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $params['hostname'],
            $params['hostport'] ?? 3306,
            $params['database'],
            $params['charset'] ?? 'utf8mb4'
        );

        $pdo = new \PDO(
            $dsn,
            $params['username'] ?? 'root',
            $params['password'] ?? ''
        );

        // 分割并执行 SQL
        $statements = $this->parseSql($sql);

        foreach ($statements as $statement) {
            if (trim($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (\PDOException $e) {
                    // 忽略表已存在的错误
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }
        }

        // 导入菜单初始数据（如果存在）
        $menuSqlFile = plugin_theadmin_path('sql/menu_init.sql');
        if (file_exists($menuSqlFile)) {
            $menuSql = file_get_contents($menuSqlFile);
            $menuSql = str_replace('th_', $params['prefix'], $menuSql);
            $menuStatements = $this->parseSql($menuSql);

            foreach ($menuStatements as $statement) {
                if (trim($statement)) {
                    try {
                        $pdo->exec($statement);
                    } catch (\PDOException $e) {
                        // 忽略重复数据错误
                        if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                            // 不抛出错误，继续执行
                        }
                    }
                }
            }
        }
    }

    /**
     * 解析 SQL 语句
     */
    protected function parseSql(string $sql): array
    {
        // 移除注释
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // 分割语句
        return array_filter(
            array_map('trim', explode(';', $sql)),
            fn($stmt) => !empty($stmt)
        );
    }

    /**
     * 创建管理员
     */
    protected function createAdmin(array $params): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $params['hostname'],
            $params['hostport'] ?? 3306,
            $params['database'],
            $params['charset'] ?? 'utf8mb4'
        );

        $pdo = new \PDO(
            $dsn,
            $params['username'] ?? 'root',
            $params['password'] ?? ''
        );

        $prefix = $params['prefix'];
        $now = date('Y-m-d H:i:s');
        $password = password_hash($params['admin_password'], PASSWORD_DEFAULT);

        // 检查管理员是否已存在
        $stmt = $pdo->prepare("SELECT id FROM {$prefix}sys_admin WHERE username = ?");
        $stmt->execute([$params['admin_username']]);

        if ($stmt->fetch()) {
            // 更新管理员密码
            $stmt = $pdo->prepare("UPDATE {$prefix}sys_admin SET password = ?, updated_at = ? WHERE username = ?");
            $stmt->execute([$password, $now, $params['admin_username']]);
        } else {
            // 创建管理员
            $stmt = $pdo->prepare("INSERT INTO {$prefix}sys_admin (username, password, nickname, status, created_at, updated_at)
                                   VALUES (?, ?, ?, 1, ?, ?)");
            $stmt->execute([
                $params['admin_username'],
                $password,
                $params['admin_nickname'] ?? '超级管理员',
                $now,
                $now,
            ]);

            $adminId = $pdo->lastInsertId();

            // 关联超级管理员角色（检查角色是否存在）
            $roleStmt = $pdo->query("SELECT id FROM {$prefix}sys_role WHERE code = 'R_SUPER' LIMIT 1");
            $role = $roleStmt->fetch(\PDO::FETCH_ASSOC);

            if ($role) {
                $stmt = $pdo->prepare("INSERT INTO {$prefix}sys_admin_role (admin_id, role_id) VALUES (?, ?)");
                $stmt->execute([$adminId, $role['id']]);
            }
        }
    }

    /**
     * 标记安装完成
     */
    protected function markAsInstalled(array $params): void
    {
        $installFile = $this->getInstallLockFile();

        $data = [
            'installed' => true,
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'database' => $params['database'],
        ];

        $dir = dirname($installFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($installFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取配置文件路径
     */
    protected function getConfigFile(): string
    {
        return plugin_theadmin_path('config/database.php');
    }

    /**
     * 获取安装锁文件路径
     */
    protected function getInstallLockFile(): string
    {
        return runtime_path('theadmin_install.lock');
    }

    /**
     * 获取安装进度
     */
    public function getProgress(): array
    {
        return [
            'step' => 1,
            'total_steps' => 5,
            'message' => '准备安装...',
        ];
    }

    /**
     * 创建 PDO 连接（用于检测）
     */
    protected function createPdoConnection(array $config): \PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['hostname'],
            $config['hostport'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        return new \PDO(
            $dsn,
            $config['username'],
            $config['password']
        );
    }
}
