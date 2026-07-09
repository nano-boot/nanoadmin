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
 * - SQL 表前缀 `na_` 统一替换为用户填写的 prefix
 * - .env/database.php 用 env() 读取，无需在 PHP 文件里塞入运行时值
 * - 所有失败抛 \RuntimeException，由 Controller 转友好提示
 */
class InstallService
{
    private const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql', 'json', 'openssl', 'mbstring'];
    private const RECOMMENDED_EXTENSIONS = ['curl', 'gd', 'fileinfo', 'zip'];
    private const MIN_PHP_VERSION = '8.1.0';
    private const MIN_COMPOSER_VERSION = '2.0.0';
    private const COMPOSER_CHECK_TIMEOUT = 2;
    private const SQL_TABLE_PREFIX = 'na_';

    private string $envPath;
    private string $lockPath;
    /** storage 目录，由 plugin\nanoadmin\Install 在 install/update 时创建 */
    private string $storagePath;
    private string $sqlPath;
    private string $menuInitSqlPath;
    /** 当前安装使用的表前缀（用户在向导中填写，与 database.php prefix 一致） */
    private string $prefix = self::SQL_TABLE_PREFIX;

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
        $commands = [
            ['composer', '--version'],
            ['composer.phar', '--version'],
            ['php', 'composer.phar', '--version'],
        ];

        foreach ($commands as $command) {
            $output = $this->runCommandWithTimeout($command, self::COMPOSER_CHECK_TIMEOUT);
            if ($output === null || stripos($output, 'composer') === false) {
                continue;
            }

            $version = $this->parseComposerVersion($output);
            if ($version !== null) {
                return $version;
            }
        }

        return null;
    }

    /**
     * 在 webman worker 里运行外部命令必须限制执行时间，并关闭 stdin。
     * 旧版 Composer 在 root / 非交互环境下可能挂起，导致 /install 请求一直不返回。
     *
     * @param string[] $command
     */
    private function runCommandWithTimeout(array $command, int $timeoutSeconds): ?string
    {
        if (!function_exists('proc_open')) {
            return null;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, base_path(), $this->composerProcessEnv());
        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $deadline = microtime(true) + max(1, $timeoutSeconds);
        $timedOut = false;

        while (true) {
            foreach ([1, 2] as $index) {
                $chunk = stream_get_contents($pipes[$index]);
                if (is_string($chunk) && $chunk !== '') {
                    $output .= $chunk;
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                break;
            }

            usleep(100000);
        }

        if ($timedOut) {
            @proc_terminate($process);
            usleep(100000);
            $status = proc_get_status($process);
            if ($status['running']) {
                @proc_terminate($process, 9);
            }
        }

        foreach ([1, 2] as $index) {
            $chunk = stream_get_contents($pipes[$index]);
            if (is_string($chunk) && $chunk !== '') {
                $output .= $chunk;
            }
            fclose($pipes[$index]);
        }
        @proc_close($process);

        return trim($output) !== '' ? $output : null;
    }

    /** @return array<string, string> */
    private function composerProcessEnv(): array
    {
        $env = getenv();
        if (!is_array($env)) {
            $env = [];
        }

        $env['COMPOSER_ALLOW_SUPERUSER'] = '1';
        $env['COMPOSER_NO_INTERACTION'] = '1';
        $env['PATH'] = $env['PATH'] ?? '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
        $env['HOME'] = $env['HOME'] ?? base_path();

        return array_map(static fn ($value) => (string) $value, $env);
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

        $prefix = $params['prefix'] ?? self::SQL_TABLE_PREFIX;
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $prefix);
        $this->prefix = $prefix ?: self::SQL_TABLE_PREFIX;

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

            // Redis 测试结果决定 think-cache.php 的 default：redis 连接成功 → 'redis'，否则 → 'file'
            $redis = $this->extractRedisParams($params);
            $redisOk = false;
            if ($redis !== []) {
                // 服务端再校验一次 redis 是否真的可达，避免前端被绕过提交无效配置
                $redisTest = $this->testRedisConnection($redis);
                $redisOk = $redisTest['success'];
            }
            $this->writeThinkCacheConfig($redisOk);

            // 无论 Redis 测试是否通过、是否填写，都统一把 config/redis.php 改写为 env() 形式；
            // 未填写 / 失败时 .env 不会写入 REDIS_*，运行时走 fallback；填写成功时 .env 写入真实值覆盖 fallback。
            $this->writeRedisConfig();

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
                'redis'   => $redisOk ? $redis : [],
                'cache'   => $redisOk ? 'redis' : 'file',
            ];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 从安装参数中读取 Redis 配置；未填写时返回空数组。
     *
     * Controller 已经把 redis 配置归一化（trim / 范围矫正），这里再
     * 二次过滤一次 "host 为空" 的边界值，避免 service 被外部直接调用时漏判。
     *
     * @return array{host:string,port:int,password:string,database:int}|array{}
     */
    private function extractRedisParams(array $params): array
    {
        if (!isset($params['redis']) || !is_array($params['redis'])) {
            return [];
        }
        $redis = $params['redis'];
        $host = trim((string) ($redis['host'] ?? ''));
        if ($host === '') {
            return [];
        }

        $port = (int) ($redis['port'] ?? 6379);
        if ($port < 1 || $port > 65535) {
            $port = 6379;
        }
        $database = (int) ($redis['database'] ?? 0);
        if ($database < 0) {
            $database = 0;
        }

        return [
            'host'     => $host,
            'port'     => $port,
            'password' => (string) ($redis['password'] ?? ''),
            'database' => $database,
        ];
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

        // Redis 是可选配置：仅当 host 非空时把 REDIS_* 段写进 .env
        $redis = $this->extractRedisParams($db);
        if ($redis !== []) {
            $map['REDIS_HOST']     = $redis['host'];
            $map['REDIS_PORT']     = (string) $redis['port'];
            $map['REDIS_PASSWORD'] = $redis['password'];
            $map['REDIS_DATABASE'] = (string) $redis['database'];
        }

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
            'prefix'      => env('DB_PREFIX', 'na_'),
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

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0
EOF;
    }

    /**
     * 把插件内的 config/think-cache.php 拷贝/覆盖到主项目 config/think-cache.php
     *
     * 设计要点：
     * - 源文件必须是已安装的插件路径 `plugin/nanoadmin/config/think-cache.php`
     *   （install 时已经由 webman Install 类的 pathRelation 同步到位）
     * - 文件不存在时创建，存在时按现有 redis.php 一样"备份 → 覆盖 → 删备份"流程做安全写入
     * - 与 redis.php / database.php 一样，回滚失败保留备份供运维处理
     *
     * `$useRedis` 决定覆盖后的 'default'：
     * - true  → 'redis'（已通过连通性测试）
     * - false → 'file'（未填写 Redis / 测试失败）
     *
     * 实现方式：保留插件原文件结构 + 'stores' 段，仅把动态判断那行替换为字面量。
     */
    public function writeThinkCacheConfig(bool $useRedis = false): void
    {
        $src = base_path() . '/plugin/nanoadmin/config/think-cache.php';
        $dst = base_path() . '/config/think-cache.php';

        if (!is_file($src)) {
            throw new \RuntimeException('插件内 think-cache.php 不存在，无法同步：' . $src);
        }

        $payload = (string) file_get_contents($src);
        if ($payload === '') {
            throw new \RuntimeException('插件内 think-cache.php 内容为空：' . $src);
        }

        // 把 `'$redisDefault ? 'redis' : 'file'` 动态分支替换为根据测试结果定下的字面量
        // 匹配的是 plugin 文件里的字面 $redisDefault，PCRE 里 $ 需要 \\\$（PHP 单引号里 \\\$ 也是 \\$，最终交给 PCRE 是 \\\$——这里用 \\\$）
        $payload = preg_replace(
            "/'default'\s*=>\s*\\\$redisDefault\s*\?\s*'redis'\s*:\s*'file'/",
            "'default' => '" . ($useRedis ? 'redis' : 'file') . "'",
            $payload,
            1
        );

        $dstDir = dirname($dst);

        if (!is_file($dst)) {
            if (!is_dir($dstDir)) {
                if (!@mkdir($dstDir, 0755, true) && !is_dir($dstDir)) {
                    throw new \RuntimeException('无法创建 config 目录：' . $dstDir);
                }
            }
            if (!is_writable($dstDir)) {
                throw new \RuntimeException('config 目录不可写，无法创建 think-cache.php：' . $dstDir);
            }

            $bytes = @file_put_contents($dst, $payload, LOCK_EX);
            if ($bytes === false) {
                throw new \RuntimeException('config/think-cache.php 创建失败：' . $dst);
            }
            return;
        }

        if (!is_writable($dst)) {
            throw new \RuntimeException('config/think-cache.php 不可写，请手动赋予写权限');
        }

        $backup = $dst . '.bak';
        if (is_file($backup)) {
            @unlink($backup);
        }
        if (!@copy($dst, $backup)) {
            throw new \RuntimeException('config/think-cache.php 备份失败，无法继续');
        }

        $bytes = @file_put_contents($dst, $payload, LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException('config/think-cache.php 写入失败，原文件已备份至 ' . $backup);
        }

        @unlink($backup);
    }

    /**
     * 测试 Redis 连通性
     *
     * 优先使用 phpredis 扩展（支持 AUTH 验证 + PING），
     * 扩展不可用时回落到 fsockopen TCP 连通性检查（AUTH 不验证）。
     *
     * @param array{host:string,port:int,password:string,database:int} $redis
     * @return array{success:bool, message:string}
     */
    public function testRedisConnection(array $redis): array
    {
        $host = trim((string) ($redis['host'] ?? ''));
        if ($host === '') {
            return ['success' => false, 'message' => 'Redis 主机不能为空'];
        }
        $port = (int) ($redis['port'] ?? 6379);
        if ($port < 1 || $port > 65535) {
            return ['success' => false, 'message' => 'Redis 端口范围必须是 1-65535'];
        }
        $password = (string) ($redis['password'] ?? '');

        if (class_exists('\Redis')) {
            try {
                $client = new \Redis();
                if (!$client->connect($host, $port, 2.0)) {
                    return ['success' => false, 'message' => "Redis 连接失败：无法连接到 {$host}:{$port}"];
                }
                if ($password !== '' && !$client->auth($password)) {
                    $client->close();
                    return ['success' => false, 'message' => 'Redis 认证失败，请检查密码'];
                }
                $pong = $client->ping();
                $client->close();
                // 不同 phpredis 版本 PING 返回 '+PONG' / true / 'PONG' 都算成功
                $ok = $pong === true || $pong === '+PONG' || $pong === 'PONG' || $pong === 1;
                if (!$ok) {
                    return ['success' => false, 'message' => 'Redis PING 未返回 PONG'];
                }
                return ['success' => true, 'message' => "Redis 连接成功（{$host}:{$port}）"];
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Redis 连接失败：' . $this->translateRedisError($e->getMessage())];
            }
        }

        // 退化路径：仅做 TCP 端口连通性检查
        $errno = 0;
        $errstr = '';
        $sock = @fsockopen($host, $port, $errno, $errstr, 2.0);
        if (!$sock) {
            $detail = $errstr !== '' ? "{$errstr} ({$errno})" : "无法连接到 {$host}:{$port}";
            return ['success' => false, 'message' => 'Redis 连接失败：' . $detail];
        }
        fclose($sock);
        return [
            'success' => true,
            'message' => "Redis 端口可达（{$host}:{$port}，未安装 phpredis 扩展，仅做 TCP 连通性测试）",
        ];
    }

    /** 翻译 phpredis 异常为友好中文 */
    private function translateRedisError(string $message): string
    {
        if (stripos($message, 'Connection refused') !== false) {
            return '连接被拒绝，请确认 Redis 已启动，主机和端口正确';
        }
        if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false) {
            return '连接超时，请确认主机、端口正确，防火墙已放行';
        }
        if (stripos($message, 'getaddrinfo failed') !== false || stripos($message, 'Name or service not known') !== false) {
            return '无法解析 Redis 主机地址';
        }
        if (stripos($message, 'NOAUTH') !== false || stripos($message, 'WRONGPASS') !== false || stripos($message, 'AUTH') !== false) {
            return '认证失败，请检查 Redis 密码';
        }
        return $message;
    }

    /**
     * 写入/更新主项目 config/redis.php
     *
     * 始终使用 env() 形式（无论 Redis 是否填写、测试是否通过），让 .env 中 REDIS_* 在运行时被读取；
     * fallback 用 webman 默认值（127.0.0.1:6379，无密码，db=0）。
     * 与 writeDatabaseConfig 一致，文件已存在时先备份再覆盖，失败回滚。
     */
    public function writeRedisConfig(): void
    {
        $configPath = base_path() . '/config/redis.php';
        $configDir = dirname($configPath);

        $payload = $this->renderRedisConfigPhp();

        if (!is_file($configPath)) {
            if (!is_dir($configDir)) {
                if (!@mkdir($configDir, 0755, true) && !is_dir($configDir)) {
                    throw new \RuntimeException('无法创建 config 目录：' . $configDir);
                }
            }
            if (!is_writable($configDir)) {
                throw new \RuntimeException('config 目录不可写，无法创建 redis.php：' . $configDir);
            }

            $bytes = @file_put_contents($configPath, $payload, LOCK_EX);
            if ($bytes === false) {
                throw new \RuntimeException('config/redis.php 创建失败：' . $configPath);
            }
            return;
        }

        if (!is_writable($configPath)) {
            throw new \RuntimeException('config/redis.php 不可写，请手动赋予写权限');
        }

        $backup = $configPath . '.bak';
        if (is_file($backup)) {
            @unlink($backup);
        }
        if (!@copy($configPath, $backup)) {
            throw new \RuntimeException('config/redis.php 备份失败，无法继续');
        }

        $bytes = @file_put_contents($configPath, $payload, LOCK_EX);
        if ($bytes === false) {
            throw new \RuntimeException('config/redis.php 写入失败，原文件已备份至 ' . $backup);
        }

        @unlink($backup);
    }

    /**
     * 渲染 config/redis.php 内容模板：
     *
     * - 与 webman 默认 redis.php 完全同构（保留 webman 文档头注释、单层 'default' 结构、连接池节点）
     * - 把 host / port / password / database 全部替换为 env() 调用，
     *   让 .env 中 REDIS_* 的值在运行时被读取；fallback 用 webman 默认值
     * - 不引入 `stores` 等额外节点，保持源码与 webman 升级兼容
     * - 'prefix' 默认 'nanoadmin:'（所有 Redis key 自动加上此前缀，便于多应用共享同一 Redis 实例），
     *   用户可在 .env 中通过 REDIS_PREFIX= 覆盖；留空字符串 '' 表示不加前缀
     */
    private function renderRedisConfigPhp(): string
    {
        // 无论用户是否填写 Redis，都统一生成 env() 形式的 config/redis.php；
        // fallback 用 webman 默认值（host=127.0.0.1 等），运行时 .env 中 REDIS_* 覆盖即可。
        return <<<'PHP'
<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    'default' => [
        'password' => env('REDIS_PASSWORD', ''),
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => (int) env('REDIS_PORT', 6379),
        'database' => (int) env('REDIS_DATABASE', 0),
        'prefix'   => env('REDIS_PREFIX', 'nanoadmin:'),
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ],
    ]
];
PHP;
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

    /** 把 SQL 中的硬编码 `na_` 前缀替换为当前 prefix（处理反引号和裸写两种形态） */
    private function replaceTablePrefix(string $statement): string
    {
        if ($this->prefix === self::SQL_TABLE_PREFIX) {
            return $statement;
        }

        $statement = str_replace('`' . self::SQL_TABLE_PREFIX, '`' . $this->prefix, $statement);
        return (string) preg_replace(
            '/(^|[\s,().])' . preg_quote(self::SQL_TABLE_PREFIX, '/') . '/i',
            '$1' . $this->prefix,
            $statement
        );
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
