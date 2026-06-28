<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\service;

use PDO;
use PDOException;
use Throwable;

/**
 * 安装服务
 *
 * 负责可视化安装向导的核心流程：
 *   1. 检测环境（PHP 版本、扩展、目录权限）
 *   2. 测试数据库连接
 *   3. 写入 .env
 *   4. 执行 sql/install.sql 建表 + 初始化数据
 *   5. 创建/更新初始管理员
 *   6. 写入 plugin/nanoadmin/storage/install.lock
 *
 * 设计原则：
 *   - 不引入 think-orm/illuminate 依赖（安装阶段框架配置可能不完整）
 *   - 使用原生 PDO 直接执行 SQL
 *   - .env 用文件锁防并发
 *   - 所有失败抛出 \RuntimeException，由 Controller 转为友好提示
 */
class InstallService
{
    /**
     * 必需的 PHP 扩展
     */
    private const REQUIRED_EXTENSIONS = [
        'pdo',
        'pdo_mysql',
        'json',
        'openssl',
        'mbstring',
    ];

    /**
     * 推荐扩展（缺失不阻断，仅提示）
     */
    private const RECOMMENDED_EXTENSIONS = [
        'curl',
        'gd',
        'fileinfo',
    ];

    /**
     * 最低 PHP 版本
     */
    private const MIN_PHP_VERSION = '8.1.0';

    /**
     * 主项目 .env 路径
     */
    private string $envPath;

    /**
     * 安装锁文件路径
     */
    private string $lockPath;

    /**
     * storage 目录
     */
    private string $storagePath;

    /**
     * SQL 文件路径
     */
    private string $sqlPath;

    public function __construct()
    {
        $this->envPath     = base_path() . '/.env';
        $this->lockPath    = base_path() . '/plugin/nanoadmin/storage/install.lock';
        $this->storagePath = base_path() . '/plugin/nanoadmin/storage';
        $this->sqlPath     = base_path() . '/plugin/nanoadmin/sql/install.sql';
    }

    /**
     * 是否已安装
     */
    public function isInstalled(): bool
    {
        return is_file($this->lockPath);
    }

    /**
     * 环境检测
     *
     * @return array{passed:bool, php:array, extensions:array, directories:array}
     */
    public function checkEnv(): array
    {
        // PHP 版本
        $phpCheck = [
            'name'    => 'PHP 版本',
            'require' => '>= ' . self::MIN_PHP_VERSION,
            'current' => PHP_VERSION,
            'status'  => version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=') ? 'ok' : 'fail',
        ];

        // 必需扩展
        $requiredChecks = [];
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $requiredChecks[$ext] = [
                'name'   => $ext,
                'status' => extension_loaded($ext) ? 'ok' : 'fail',
            ];
        }

        // 推荐扩展
        $recommendedChecks = [];
        foreach (self::RECOMMENDED_EXTENSIONS as $ext) {
            $recommendedChecks[$ext] = [
                'name'   => $ext,
                'status' => extension_loaded($ext) ? 'ok' : 'warning',
            ];
        }

        // 目录可写性
        $dirs = [
            'env'        => base_path() . '/.env',
            'database_php' => base_path() . '/config/database.php',
            'storage'    => $this->storagePath,
            'config'     => base_path() . '/config',
        ];

        $directoryChecks = [];
        foreach ($dirs as $key => $path) {
            if (is_file($path)) {
                $writable = is_writable($path);
            } else {
                $parent = dirname($path);
                $writable = is_dir($parent) && is_writable($parent);
            }
            $directoryChecks[$key] = [
                'name'   => $path,
                'status' => $writable ? 'ok' : 'fail',
            ];
        }

        // 总判定
        $passed = $phpCheck['status'] === 'ok'
            && !in_array('fail', array_column($requiredChecks, 'status'), true)
            && !in_array('fail', array_column($directoryChecks, 'status'), true);

        return [
            'passed'     => $passed,
            'php'        => $phpCheck,
            'extensions' => [
                'required'    => $requiredChecks,
                'recommended' => $recommendedChecks,
            ],
            'directories' => $directoryChecks,
        ];
    }

    /**
     * 测试数据库连接（不创建数据库）
     *
     * @return array{success:bool, message:string, server_version?:string}
     */
    public function testDatabaseConnection(array $db): array
    {
        $this->validateDbParams($db);

        $dsn = sprintf('mysql:host=%s;port=%s', $db['host'], $db['port']);

        try {
            $pdo = new PDO($dsn, $db['user'], $db['password'], [
                PDO::ATTR_TIMEOUT            => 5,
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8mb4",
            ]);

            // 探测数据库是否存在（不创建）
            $stmt = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($db['name']));
            $exists = (bool) $stmt->fetchColumn();

            return [
                'success'        => true,
                'message'        => $exists
                    ? '数据库连接成功（数据库已存在）'
                    : '数据库连接成功（数据库不存在，安装时将自动创建）',
                'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'db_exists'      => $exists,
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => $this->translatePdoError($e->getMessage()),
            ];
        }
    }

    /**
     * 执行安装主流程
     *
     * @param array $params
     *   - host, port, name, user, password, prefix：数据库连接
     *   - admin_user, admin_password, admin_nickname：初始管理员
     * @return array{success:bool, message:string, admin?:array}
     */
    public function runInstallation(array $params): array
    {
        $this->validateDbParams($params);
        $this->validateAdminParams($params);

        if ($this->isInstalled()) {
            throw new \RuntimeException('系统已安装，无需重复安装');
        }

        // 并发防护：文件锁
        $lockFile = $this->storagePath . '/install.flock';
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
        $fp = fopen($lockFile, 'c');
        if (!$fp || !flock($fp, LOCK_EX)) {
            throw new \RuntimeException('另一个安装流程正在进行，请稍后再试');
        }

        try {
            // 1. 测试连接
            $test = $this->testDatabaseConnection($params);
            if (!$test['success']) {
                throw new \RuntimeException($test['message']);
            }

            // 2. 连接数据库（自动创建库）
            $pdo = $this->connectAndCreateDatabase($params);

            // 3. 写入 .env
            $this->writeEnv($params);

            // 4. 执行 install.sql
            $this->runInstallSql($pdo, $params['name']);

            // 5. 创建/更新初始管理员
            $this->createAdmin($pdo, $params);

            // 6. 写入 install.lock
            $this->writeLockFile();

            return [
                'success' => true,
                'message' => '安装成功',
                'admin'   => [
                    'username' => $params['admin_user'],
                    'nickname' => $params['admin_nickname'] ?? '超级管理员',
                ],
            ];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 校验数据库参数
     */
    private function validateDbParams(array $db): void
    {
        foreach (['host', 'port', 'name', 'user'] as $field) {
            if (empty($db[$field])) {
                throw new \RuntimeException("数据库参数 [{$field}] 不能为空");
            }
        }
    }

    /**
     * 校验管理员参数
     */
    private function validateAdminParams(array $params): void
    {
        if (empty($params['admin_user'])) {
            throw new \RuntimeException('管理员账号不能为空');
        }
        if (empty($params['admin_password'])) {
            throw new \RuntimeException('管理员密码不能为空');
        }
        if (strlen($params['admin_password']) < 6) {
            throw new \RuntimeException('管理员密码长度不能少于 6 位');
        }
        if (isset($params['admin_password_confirm'])
            && $params['admin_password'] !== $params['admin_password_confirm']) {
            throw new \RuntimeException('两次输入的密码不一致');
        }
    }

    /**
     * 连接数据库，必要时自动创建
     */
    private function connectAndCreateDatabase(array $db): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']);
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_TIMEOUT            => 5,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "set names utf8mb4",
        ]);

        $stmt = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($db['name']));
        if (!$stmt->fetchColumn()) {
            $dbName = str_replace('`', '``', $db['name']);
            $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $pdo->exec("USE `{$db['name']}`");
        return $pdo;
    }

    /**
     * 写入或 patch 主项目 .env
     *
     * 已有 .env 则精确替换 DB_* 段；不存在则从 .env.example 复制模板，再注入。
     * config/database.php 通过 env() 函数读取这些变量，无需改动。
     */
    public function writeEnv(array $db): void
    {
        // 加载模板
        if (!is_file($this->envPath)) {
            $template = base_path() . '/.env.example';
            if (is_file($template)) {
                copy($template, $this->envPath);
            } else {
                file_put_contents($this->envPath, $this->defaultEnvTemplate());
            }
        }

        $content = file_get_contents($this->envPath);

        $map = [
            'DB_HOST'     => $db['host'],
            'DB_PORT'     => $db['port'],
            'DB_DATABASE' => $db['name'],
            'DB_USERNAME' => $db['user'],
            'DB_PASSWORD' => $db['password'] ?? '',
            'DB_PREFIX'   => $db['prefix'] ?? '',
            'DB_CHARSET'  => 'utf8mb4',
        ];

        foreach ($map as $key => $value) {
            // 已有 KEY：替换值
            $pattern = '/^' . preg_quote($key, '/') . '\s*=.*$/m';
            $replacement = $key . '=' . $this->escapeEnvValue((string) $value);
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                // 不存在：追加到文件末尾
                $content .= PHP_EOL . $replacement;
            }
        }

        file_put_contents($this->envPath, $content, LOCK_EX);
    }

    /**
     * 转义 .env 值（处理空格、# 等特殊字符）
     */
    private function escapeEnvValue(string $value): string
    {
        if (preg_match('/[\s#"\\\\]/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        return $value;
    }

    /**
     * 默认 .env 模板（无 .env.example 时使用）
     */
    private function defaultEnvTemplate(): string
    {
        return <<<'EOF'
APP_NAME=NanoAdmin
APP_ENV=local
APP_DEBUG=true
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nanoadmin
DB_USERNAME=root
DB_PASSWORD=
DB_PREFIX=
DB_CHARSET=utf8mb4
EOF;
    }

    /**
     * 执行 sql/install.sql
     *
     * SQL 文件以 `;` 分隔每条语句，但 MySQL 的存储过程 / DELIMITER 不存在，
     * 我们的 install.sql 只含标准 DDL/DML，可以按 `;` 切分后逐条执行。
     */
    public function runInstallSql(PDO $pdo, string $database): void
    {
        if (!is_file($this->sqlPath)) {
            throw new \RuntimeException('SQL 文件不存在: ' . $this->sqlPath);
        }

        $sql = file_get_contents($this->sqlPath);
        if ($sql === false) {
            throw new \RuntimeException('SQL 文件读取失败');
        }

        // 过滤 USE 和 CREATE DATABASE 语句（已在外层处理）
        $statements = $this->splitSql($sql);

        $pdo->beginTransaction();
        try {
            foreach ($statements as $statement) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // 重复建表/键等"幂等失败"忽略
                    if (!$this->isIdempotentError($e)) {
                        throw $e;
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new \RuntimeException(
                '执行 SQL 失败: ' . $e->getMessage() . ' (SQL: ' . substr($e->getMessage(), 0, 100) . ')',
                0,
                $e
            );
        }
    }

    /**
     * 按 `;` 切分 SQL，过滤注释和 USE/CREATE DATABASE
     *
     * @return string[]
     */
    private function splitSql(string $sql): array
    {
        // 移除 `--` 注释
        $lines = preg_replace('/^\s*--.*$/m', '', $sql);
        // 移除多行注释
        $lines = preg_replace('/\/\*.*?\*\//s', '', (string) $lines);

        $parts = explode(';', (string) $lines);
        $statements = [];
        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '') {
                continue;
            }
            // 跳过 CREATE DATABASE / USE
            if (preg_match('/^\s*(CREATE\s+DATABASE|USE)\s/i', $trimmed)) {
                continue;
            }
            $statements[] = $trimmed;
        }
        return $statements;
    }

    /**
     * 是否幂等错误（重复建表/键），可以忽略
     */
    private function isIdempotentError(PDOException $e): bool
    {
        $msg = $e->getMessage();
        $patterns = [
            'already exists',
            'Duplicate key name',
            'Duplicate entry',
            'multiple primary key',
            "Table .* doesn't exist",  // DROP TABLE IF EXISTS 时
        ];
        foreach ($patterns as $pattern) {
            if (stripos($msg, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 创建/更新初始管理员
     */
    public function createAdmin(PDO $pdo, array $params): void
    {
        $username = $params['admin_user'];
        $password = password_hash($params['admin_password'], PASSWORD_DEFAULT);
        $nickname = $params['admin_nickname'] ?? '超级管理员';
        $now      = date('Y-m-d H:i:s');

        // 覆盖 install.sql 中默认 admin/system 账户，统一为用户填写的账号
        $sql = "INSERT INTO `th_sys_admin` (`id`, `username`, `password`, `nickname`, `status`, `deleted`, `created_at`, `updated_at`)
                VALUES (1, ?, ?, ?, 1, 0, ?, ?)
                ON DUPLICATE KEY UPDATE
                    `username`   = VALUES(`username`),
                    `password`   = VALUES(`password`),
                    `nickname`   = VALUES(`nickname`),
                    `status`     = 1,
                    `deleted`    = 0,
                    `updated_at` = VALUES(`updated_at`)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $password, $nickname, $now, $now]);

        // 确保 admin_role 关联存在
        $roleCheck = $pdo->query("SELECT id FROM `th_sys_role` WHERE `code` = 'R_SUPER' LIMIT 1");
        $superRole = $roleCheck ? $roleCheck->fetchColumn() : null;

        if ($superRole) {
            $assocSql = "INSERT IGNORE INTO `th_sys_admin_role` (`admin_id`, `role_id`) VALUES (1, ?)";
            $assocStmt = $pdo->prepare($assocSql);
            $assocStmt->execute([$superRole]);
        }
    }

    /**
     * 写入安装锁文件
     */
    public function writeLockFile(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
        $content = json_encode([
            'installed_at' => date('c'),
            'version'      => '1.0.0',
            'php_version'  => PHP_VERSION,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        file_put_contents($this->lockPath, $content, LOCK_EX);
    }

    /**
     * 翻译 PDO 错误信息为更友好的中文提示
     */
    private function translatePdoError(string $message): string
    {
        if (stripos($message, 'Access denied for user') !== false) {
            return '数据库用户名或密码错误';
        }
        if (stripos($message, 'Connection refused') !== false) {
            return '数据库连接被拒绝，请确认主机和端口是否正确，数据库服务已启动';
        }
        if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false) {
            return '数据库连接超时，请确认主机、端口是否正确，防火墙已放行端口';
        }
        if (stripos($message, 'getaddrinfo failed') !== false || stripos($message, 'Unknown MySQL server') !== false) {
            return '无法解析数据库主机地址，请确认主机名/IP 正确';
        }
        if (stripos($message, 'SQLSTATE') !== false) {
            return '数据库错误: ' . $message;
        }
        return $message;
    }
}
