<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\service;

use PDO;
use PDOException;
use Throwable;

/**
 * 可视化安装向导核心服务
 *
 * 流程：环境检测 → 数据库连接测试 → 写入 .env/database.php →
 *       执行 install.sql 建表 → 写入菜单 → 绑定超管角色 → 创建管理员 → 写锁文件
 *
 * 设计原则：
 * - 安装阶段不走 webman 框架（配置可能不完整），用原生 PDO 直连
 * - SQL 表前缀 `th_` 统一替换为用户填写的 prefix
 * - .env/database.php 用 env() 读取，无需在 PHP 文件里塞入运行时值
 * - 所有失败抛 \RuntimeException，由 Controller 转友好提示
 */
class InstallService
{
    private const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql', 'json', 'openssl', 'mbstring'];
    private const RECOMMENDED_EXTENSIONS = ['curl', 'gd', 'fileinfo', 'zip'];
    private const MIN_PHP_VERSION = '8.1.0';
    private const MIN_COMPOSER_VERSION = '2.0.0';

    private string $envPath;
    private string $lockPath;
    /** storage 目录，由 Webman\nanoadmin\Install 在 install/update 时创建 */
    private string $storagePath;
    private string $sqlPath;
    private string $menuInitSqlPath;
    /** 当前安装使用的表前缀（用户在向导中填写，与 database.php prefix 一致） */
    private string $prefix = 'th_';

    public function __construct()
    {
        $this->envPath          = base_path() . '/.env';
        $this->lockPath         = base_path() . '/storage/install.lock';
        $this->storagePath      = base_path() . '/storage';
        $this->sqlPath          = base_path() . '/plugin/nanoadmin/sql/install.sql';
        $this->menuInitSqlPath  = base_path() . '/plugin/nanoadmin/sql/menu_init.sql';
    }

    public function isInstalled(): bool
    {
        return is_file($this->lockPath);
    }

    /**
     * @return array{
     *   passed:bool,
     *   php:array{name:string,require:string,current:string,status:string},
     *   composer:array{name:string,require:string,current:string,status:string},
     *   extensions:array{required:array<string,array{name:string,require:string,status:string}>,recommended:array<string,array{name:string,require:string,status:string}>},
     *   directories:array<string,array{name:string,path:string,status:string}>
     * }
     */
    public function checkEnv(): array
    {
        $phpCheck = [
            'name'    => 'PHP 版本',
            'require' => '>= ' . self::MIN_PHP_VERSION,
            'current' => PHP_VERSION,
            'status'  => version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=') ? 'ok' : 'fail',
        ];

        $composerCheck = $this->checkComposer();

        $requiredChecks = [];
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $requiredChecks[$ext] = [
                'name'    => $ext . ' 扩展',
                'require' => '必须',
                'status'  => extension_loaded($ext) ? 'ok' : 'fail',
            ];
        }

        $recommendedChecks = [];
        foreach (self::RECOMMENDED_EXTENSIONS as $ext) {
            $recommendedChecks[$ext] = [
                'name'    => $ext . ' 扩展',
                'require' => '建议',
                'status'  => extension_loaded($ext) ? 'ok' : 'warning',
            ];
        }

        // 检查路径自身是否可写；文件不存在时回落到父目录可写性（便于首次安装通过检测）
        $paths = [
            'env'          => ['name' => '.env 文件',            'path' => $this->envPath],
            'database_php' => ['name' => 'config/database.php', 'path' => base_path() . '/config/database.php'],
            'storage'      => ['name' => 'storage 目录',       'path' => $this->storagePath],
            'config'       => ['name' => 'config 目录（插件配置）', 'path' => base_path() . '/config'],
        ];

        $directoryChecks = [];
        foreach ($paths as $key => $meta) {
            $path = $meta['path'];
            if (is_file($path)) {
                $writable = is_writable($path);
            } else {
                $parent = dirname($path);
                $writable = is_dir($parent) && is_writable($parent);
            }
            $directoryChecks[$key] = [
                'name'   => $meta['name'],
                'path'   => $path,
                'status' => $writable ? 'ok' : 'fail',
            ];
        }

        $passed = $phpCheck['status'] === 'ok'
            && $composerCheck['status'] !== 'fail'
            && !in_array('fail', array_column($requiredChecks, 'status'), true)
            && !in_array('fail', array_column($directoryChecks, 'status'), true);

        return [
            'passed'      => $passed,
            'php'         => $phpCheck,
            'composer'    => $composerCheck,
            'extensions'  => ['required' => $requiredChecks, 'recommended' => $recommendedChecks],
            'directories' => $directoryChecks,
        ];
    }

    /** @return array{name:string,require:string,current:string,status:string} */
    private function checkComposer(): array
    {
        $version = $this->detectComposerVersion();
        if ($version === null) {
            return [
                'name'    => 'Composer',
                'require' => '>= ' . self::MIN_COMPOSER_VERSION,
                'current' => '未检测到',
                'status'  => 'fail',
            ];
        }

        return [
            'name'    => 'Composer',
            'require' => '>= ' . self::MIN_COMPOSER_VERSION,
            'current' => $version,
            'status'  => version_compare($version, self::MIN_COMPOSER_VERSION, '>=') ? 'ok' : 'fail',
        ];
    }

    /** 依次尝试 composer / composer.phar / php composer.phar，返回版本号或 null */
    private function detectComposerVersion(): ?string
    {
        $candidates = ['composer', 'composer.phar'];

        foreach ($candidates as $bin) {
            $output = @shell_exec(escapeshellcmd($bin) . ' --version 2>&1');
            if (is_string($output) && $output !== '' && stripos($output, 'composer') !== false) {
                $v = $this->parseComposerVersion($output);
                if ($v !== null) {
                    return $v;
                }
            }

            $path = @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null');
            if (is_string($path) && trim($path) !== '') {
                $output2 = @shell_exec(escapeshellcmd(trim($path)) . ' --version 2>&1');
                if (is_string($output2) && $output2 !== '') {
                    $v = $this->parseComposerVersion($output2);
                    if ($v !== null) {
                        return $v;
                    }
                }
            }

            if ($bin === 'composer.phar') {
                $output3 = @shell_exec('php ' . escapeshellarg($bin) . ' --version 2>&1');
                if (is_string($output3) && $output3 !== '') {
                    $v = $this->parseComposerVersion($output3);
                    if ($v !== null) {
                        return $v;
                    }
                }
            }
        }

        return null;
    }

    /** 从 composer --version 输出中提取版本号，如 "2.6.5" */
    private function parseComposerVersion(string $output): ?string
    {
        if (preg_match('/Composer[^\d]*(\d+\.\d+(?:\.\d+)?)/i', $output, $m)) {
            return $m[1];
        }
        return null;
    }

    /** @return array{success:bool, message:string, server_version?:string, db_exists?:bool} */
    public function testDatabaseConnection(array $db): array
    {
        $this->validateDbParams($db);

        $dsn = sprintf('mysql:host=%s;port=%s', $db['host'], $db['port']);

        try {
            $pdo = new PDO($dsn, $db['user'], $db['password'], [
                PDO::ATTR_TIMEOUT            => 5,
                PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8mb4',
            ]);

            $stmt = $pdo->query('SHOW DATABASES LIKE ' . $pdo->quote($db['name']));
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
            return ['success' => false, 'message' => $this->translatePdoError($e->getMessage())];
        }
    }

    /**
     * @param array $params host,port,name,user,password,prefix + admin_user,admin_password,admin_nickname
     * @return array{success:bool, message:string, admin?:array}
     */
    public function runInstallation(array $params): array
    {
        $this->validateDbParams($params);
        $this->validateAdminParams($params);

        $prefix = $params['prefix'] ?? 'na_';
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $prefix);
        $this->prefix = $prefix ?: 'na_';

        if ($this->isInstalled()) {
            throw new \RuntimeException('系统已安装，无需重复安装');
        }

        $lockFile = $this->storagePath . '/install.flock';
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
        $fp = fopen($lockFile, 'c');
        if (!$fp || !flock($fp, LOCK_EX)) {
            throw new \RuntimeException('另一个安装流程正在进行，请稍后再试');
        }

        try {
            $test = $this->testDatabaseConnection($params);
            if (!$test['success']) {
                throw new \RuntimeException($test['message']);
            }

            $pdo = $this->connectAndCreateDatabase($params);

            // 写入 .env / database.php 必须先于 SQL（因为 env() 依赖这些变量）
            $this->writeEnv($params);
            $this->writeDatabaseConfig($params);

            $this->runInstallSql($pdo, $params['name']);
            $this->runMenuInitSql($pdo);
            $this->bindSuperRoleMenus($pdo);
            $this->createAdmin($pdo, $params);
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

    private function validateDbParams(array $db): void
    {
        foreach (['host', 'port', 'name', 'user'] as $field) {
            if (empty($db[$field])) {
                throw new \RuntimeException("数据库参数 [{$field}] 不能为空");
            }
        }
    }

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

    /** 连接数据库，数据库不存在时自动创建 */
    private function connectAndCreateDatabase(array $db): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']);
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_TIMEOUT            => 5,
            PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8mb4',
        ]);

        $stmt = $pdo->query('SHOW DATABASES LIKE ' . $pdo->quote($db['name']));
        if (!$stmt->fetchColumn()) {
            $dbName = str_replace('`', '``', $db['name']);
            $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $pdo->exec("USE `{$db['name']}`");
        return $pdo;
    }

    /**
     * 写入/更新主项目 .env
     *
     * 已有 .env 则精确替换 DB_* 段；不存在则从 .env.example 复制模板，无模板则用内置默认内容。
     * config/database.php 通过 env() 读取这些变量。
     */
    public function writeEnv(array $db): void
    {
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
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'       => $db['host'],
            'DB_PORT'       => $db['port'],
            'DB_DATABASE'   => $db['name'],
            'DB_USERNAME'   => $db['user'],
            'DB_PASSWORD'   => $db['password'] ?? '',
            'DB_PREFIX'     => $db['prefix'] ?? '',
            'DB_CHARSET'    => 'utf8mb4',
        ];

        foreach ($map as $key => $value) {
            $pattern = '/^' . preg_quote($key, '/') . '\s*=.*$/m';
            $replacement = $key . '=' . $this->escapeEnvValue((string) $value);
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                $content .= PHP_EOL . $replacement;
            }
        }

        file_put_contents($this->envPath, $content, LOCK_EX);
    }

    /** 转义 .env 值（处理空格、# 等特殊字符） */
    private function escapeEnvValue(string $value): string
    {
        if (preg_match('/[\s#"\\\\]/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        return $value;
    }

    /**
     * 写入/创建主项目 config/database.php
     *
     * 文件不存在时创建（mkdir + 直接写）；存在时先备份再覆盖，
     * 写入成功清理备份，失败保留备份供回滚。
     */
    public function writeDatabaseConfig(array $db): void
    {
        $configPath = base_path() . '/config/database.php';
        $configDir = dirname($configPath);

        if (!is_file($configPath)) {
            if (!is_dir($configDir)) {
                if (!@mkdir($configDir, 0755, true) && !is_dir($configDir)) {
                    throw new \RuntimeException('无法创建 config 目录：' . $configDir);
                }
            }
            if (!is_writable($configDir)) {
                throw new \RuntimeException('config 目录不可写，无法创建 database.php：' . $configDir);
            }

            $bytes = @file_put_contents($configPath, $this->renderDatabaseConfigPhp($db), LOCK_EX);
            if ($bytes === false) {
                throw new \RuntimeException('config/database.php 创建失败：' . $configPath);
            }
            return;
        }

        if (!is_writable($configPath)) {
            throw new \RuntimeException('config/database.php 不可写，请手动赋予写权限');
        }

        $backup = $configPath . '.bak';
        if (is_file($backup)) {
            @unlink($backup);
        }
        if (!@copy($configPath, $backup)) {
            throw new \RuntimeException('config/database.php 备份失败，无法继续');
        }

        $bytes = @file_put_contents($configPath, $this->renderDatabaseConfigPhp($db), LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException('config/database.php 写入失败，原文件已备份至 ' . $backup);
        }

        @unlink($backup);
    }

    /** 渲染 config/database.php 内容模板（使用 env() 读取 .env，与 webman 默认结构一致） */
    private function renderDatabaseConfigPhp(array $db): string
    {
        unset($db); // 本文件最终形态是 env() 调用，用户输入已写入 .env
        return <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver'      => env('DB_CONNECTION', 'mysql'),
            'host'        => env('DB_HOST', '127.0.0.1'),
            'port'        => (int) env('DB_PORT', 3306),
            'database'    => env('DB_DATABASE', ''),
            'username'    => env('DB_USERNAME', ''),
            'password'    => env('DB_PASSWORD', ''),
            'charset'     => env('DB_CHARSET', 'utf8mb4'),
            'collation'   => 'utf8mb4_general_ci',
            'prefix'      => env('DB_PREFIX', ''),
            'strict'      => true,
            'engine'      => null,
            'options'     => [
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
            'pool' => [
                'max_connections'    => 5,
                'min_connections'    => 1,
                'wait_timeout'       => 3,
                'idle_timeout'       => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];
PHP;
    }

    /** 无 .env.example 时的内置默认模板 */
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
     * 执行 sql/install.sql（建表 + 演示数据）
     *
     * MySQL DDL 语句会隐式提交事务，导致 PDO 视角的事务在第一条 DDL 后就结束了；
     * 用 inTransaction() 守卫避免对已结束的事务调用 rollBack / commit。
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

        $statements = $this->splitSql($sql);

        $pdo->beginTransaction();
        try {
            foreach ($statements as $statement) {
                $statement = $this->replaceTablePrefix($statement);
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    if (!$this->isIdempotentError($e)) {
                        throw $e;
                    }
                }
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \RuntimeException(
                '执行 SQL 失败: ' . $e->getMessage() . ' (SQL: ' . substr($e->getMessage(), 0, 100) . ')',
                0,
                $e
            );
        }
    }

    /**
     * 执行 sql/menu_init.sql（写入系统菜单）
     *
     * 缺失时仅记录告警，不阻断安装；重复键错误可忽略（REPLACE INTO）。
     */
    public function runMenuInitSql(PDO $pdo): void
    {
        if (!is_file($this->menuInitSqlPath)) {
            error_log('[Install] menu_init.sql 不存在，跳过菜单初始化: ' . $this->menuInitSqlPath);
            return;
        }

        $sql = file_get_contents($this->menuInitSqlPath);
        if ($sql === false) {
            throw new \RuntimeException('菜单 SQL 文件读取失败');
        }

        foreach ($this->splitSql($sql) as $statement) {
            $statement = $this->replaceTablePrefix(trim($statement));
            if ($statement === '') {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate') === false
                    && stripos($e->getMessage(), 'already exists') === false) {
                    throw new \RuntimeException('执行菜单 SQL 失败: ' . $e->getMessage(), 0, $e);
                }
            }
        }
    }

    /** 将所有菜单绑定到 R_SUPER 角色（INSERT IGNORE 幂等） */
    public function bindSuperRoleMenus(PDO $pdo): void
    {
        $roleTable = $this->prefix . 'sys_role';
        $menuTable = $this->prefix . 'sys_menu';
        $roleMenuTable = $this->prefix . 'sys_role_menu';

        $stmt = $pdo->prepare("SELECT id FROM `{$roleTable}` WHERE `code` = ? LIMIT 1");
        $stmt->execute(['R_SUPER']);
        $roleId = $stmt->fetchColumn();
        if (!$roleId) {
            return;
        }

        $stmt = $pdo->query("SELECT id FROM `{$menuTable}` WHERE `deleted` = 0");
        $menuIds = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        if (empty($menuIds)) {
            return;
        }

        $bindStmt = $pdo->prepare(
            "INSERT IGNORE INTO `{$roleMenuTable}` (`role_id`, `menu_id`) VALUES (?, ?)"
        );
        foreach ($menuIds as $menuId) {
            $bindStmt->execute([$roleId, $menuId]);
        }
    }

    /** 把 SQL 中的硬编码 `th_` 前缀替换为当前 prefix（处理反引号和裸写两种形态） */
    private function replaceTablePrefix(string $statement): string
    {
        $statement = str_replace('`th_', '`' . $this->prefix, $statement);
        return preg_replace('/(^|[\s,()])th_/i', '$1' . $this->prefix, $statement);
    }

    /** 按 ; 切分 SQL，跳过注释和 USE/CREATE DATABASE */
    private function splitSql(string $sql): array
    {
        $lines = preg_replace('/^\s*--.*$/m', '', $sql);
        $lines = preg_replace('/\/\*.*?\*\//s', '', (string) $lines);

        $statements = [];
        foreach (explode(';', (string) $lines) as $part) {
            $trimmed = trim($part);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^\s*(CREATE\s+DATABASE|USE)\s/i', $trimmed)) {
                continue;
            }
            $statements[] = $trimmed;
        }
        return $statements;
    }

    /** 是否幂等错误（重复建表/键，可忽略） */
    private function isIdempotentError(PDOException $e): bool
    {
        $msg = $e->getMessage();
        foreach (['already exists', 'Duplicate key name', 'Duplicate entry', 'multiple primary key', "Table .* doesn't exist"] as $p) {
            if (stripos($msg, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 创建/更新初始管理员（覆盖 install.sql 自带的演示账户）
     *
     * 使用 ON DUPLICATE KEY UPDATE 保证幂等。
     */
    public function createAdmin(PDO $pdo, array $params): void
    {
        $username = $params['admin_user'];
        $password = password_hash($params['admin_password'], PASSWORD_DEFAULT);
        $nickname = $params['admin_nickname'] ?? '超级管理员';
        $now = date('Y-m-d H:i:s');

        $adminTable = $this->prefix . 'sys_admin';
        $roleTable = $this->prefix . 'sys_role';
        $adminRoleTable = $this->prefix . 'sys_admin_role';

        $sql = "INSERT INTO `{$adminTable}` (`id`, `username`, `password`, `nickname`, `status`, `deleted`, `created_at`, `updated_at`)
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

        $roleCheck = $pdo->prepare("SELECT id FROM `{$roleTable}` WHERE `code` = ? LIMIT 1");
        $roleCheck->execute(['R_SUPER']);
        $superRole = $roleCheck->fetchColumn();
        if ($superRole) {
            $assocStmt = $pdo->prepare(
                "INSERT IGNORE INTO `{$adminRoleTable}` (`admin_id`, `role_id`) VALUES (1, ?)"
            );
            $assocStmt->execute([$superRole]);
        }
    }

    /** 写入 storage/install.lock */
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

    /** 将 PDO 错误信息翻译为友好中文 */
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
