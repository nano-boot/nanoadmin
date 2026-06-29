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
 *   4. 执行 sql/install.sql 建表 + 初始化数据（带表前缀替换）
 *   5. 执行 sql/menu_init.sql 写入系统菜单
 *   6. 把所有菜单绑定到 R_SUPER 角色
 *   7. 创建/更新初始管理员
 *   8. 写入 storage/install.lock
 *
 * 设计原则：
 *   - 不引入 think-orm/illuminate 依赖（安装阶段框架配置可能不完整）
 *   - 使用原生 PDO 直接执行 SQL
 *   - SQL 中硬编码的 `th_` 前缀统一替换为用户输入的 prefix
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
        'zip',
    ];

    /**
     * 最低 PHP 版本
     */
    private const MIN_PHP_VERSION = '8.1.0';

    /**
     * 最低 Composer 版本
     */
    private const MIN_COMPOSER_VERSION = '2.0.0';

    /**
     * 主项目 .env 路径
     */
    private string $envPath;

    /**
     * 安装锁文件路径
     */
    private string $lockPath;

    /**
     * storage 目录（主项目根下的 storage/，由 Webman\nanoadmin\Install 在 install/update 时创建）
     */
    private string $storagePath;

    /**
     * SQL 文件路径
     */
    private string $sqlPath;

    /**
     * 菜单初始化 SQL 文件路径
     */
    private string $menuInitSqlPath;

    /**
     * 当前安装任务使用的表前缀（用户在向导中填写，与 config/database.php 中 prefix 一致）
     */
    private string $prefix = 'th_';

    public function __construct()
    {
        $this->envPath          = base_path() . '/.env';
        $this->lockPath         = base_path() . '/storage/install.lock';
        $this->storagePath      = base_path() . '/storage';
        $this->sqlPath          = base_path() . '/plugin/nanoadmin/sql/install.sql';
        $this->menuInitSqlPath  = base_path() . '/plugin/nanoadmin/sql/menu_init.sql';
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
        // PHP 版本
        $phpCheck = [
            'name'    => 'PHP 版本',
            'require' => '>= ' . self::MIN_PHP_VERSION,
            'current' => PHP_VERSION,
            'status'  => version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=') ? 'ok' : 'fail',
        ];

        // Composer 版本
        $composerCheck = $this->checkComposer();

        // 必需扩展
        $requiredChecks = [];
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $requiredChecks[$ext] = [
                'name'    => $ext . ' 扩展',
                'require' => '必须',
                'status'  => extension_loaded($ext) ? 'ok' : 'fail',
            ];
        }

        // 推荐扩展
        $recommendedChecks = [];
        foreach (self::RECOMMENDED_EXTENSIONS as $ext) {
            $recommendedChecks[$ext] = [
                'name'    => $ext . ' 扩展',
                'require' => '建议',
                'status'  => extension_loaded($ext) ? 'ok' : 'warning',
            ];
        }

        // 目录可写性
        $dirs = [
            'env'          => ['name' => '.env 文件',     'path' => base_path() . '/.env'],
            'database_php' => ['name' => 'config/database.php', 'path' => base_path() . '/config/database.php'],
            'storage'      => ['name' => 'storage 目录',  'path' => $this->storagePath],
            'config'       => ['name' => 'config 目录',   'path' => base_path() . '/config'],
        ];

        $directoryChecks = [];
        foreach ($dirs as $key => $meta) {
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

        // 总判定：PHP、Composer、必需扩展、目录任一失败即不通过；推荐扩展不计入
        $passed = $phpCheck['status'] === 'ok'
            && $composerCheck['status'] !== 'fail'
            && !in_array('fail', array_column($requiredChecks, 'status'), true)
            && !in_array('fail', array_column($directoryChecks, 'status'), true);

        return [
            'passed'     => $passed,
            'php'        => $phpCheck,
            'composer'   => $composerCheck,
            'extensions' => [
                'required'    => $requiredChecks,
                'recommended' => $recommendedChecks,
            ],
            'directories' => $directoryChecks,
        ];
    }

    /**
     * Composer 版本检测
     *
     * 通过 `composer --version` 解析版本号；找不到 composer 可执行文件视为缺失。
     * Composer 是 webman + nanoadmin 插件运行的必要工具，必须 >= 2.0。
     *
     * @return array{name:string,require:string,current:string,status:string}
     */
    private function checkComposer(): array
    {
        $name    = 'Composer';
        $require = '>= ' . self::MIN_COMPOSER_VERSION;

        // 尝试通过 shell 调用 composer --version
        $version = $this->detectComposerVersion();
        if ($version === null) {
            return [
                'name'    => $name,
                'require' => $require,
                'current' => '未检测到',
                'status'  => 'fail',
            ];
        }

        $status = version_compare($version, self::MIN_COMPOSER_VERSION, '>=') ? 'ok' : 'fail';

        return [
            'name'    => $name,
            'require' => $require,
            'current' => $version,
            'status'  => $status,
        ];
    }

    /**
     * 调用 `composer --version` 获取版本号
     *
     * 多路尝试以适配不同环境：
     *   1. shell 直接调用 `composer`（最常见）
     *   2. `composer.phar`（部分项目把 phar 放在 vendor/bin）
     *   3. `php composer.phar --version`
     *
     * @return string|null 版本号（如 "2.6.5"），检测失败返回 null
     */
    private function detectComposerVersion(): ?string
    {
        $candidates = ['composer', 'composer.phar'];

        foreach ($candidates as $bin) {
            // 优先用 shell_exec（最简洁），捕获错误
            $output = @shell_exec(escapeshellcmd($bin) . ' --version 2>&1');
            if (is_string($output) && $output !== '' && stripos($output, 'composer') !== false) {
                $version = $this->parseComposerVersion($output);
                if ($version !== null) {
                    return $version;
                }
            }

            // 兜底：用 `which` / `command -v` 找绝对路径
            $path = @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null');
            if (is_string($path) && trim($path) !== '') {
                $absolute = trim($path);
                $output2   = @shell_exec(escapeshellcmd($absolute) . ' --version 2>&1');
                if (is_string($output2) && $output2 !== '') {
                    $version = $this->parseComposerVersion($output2);
                    if ($version !== null) {
                        return $version;
                    }
                }
            }

            // 最后兜底：php composer.phar
            if ($bin === 'composer.phar') {
                $output3 = @shell_exec('php ' . escapeshellcmd($bin) . ' --version 2>&1');
                if (is_string($output3) && $output3 !== '') {
                    $version = $this->parseComposerVersion($output3);
                    if ($version !== null) {
                        return $version;
                    }
                }
            }
        }

        return null;
    }

    /**
     * 从 `composer --version` 输出中提取语义化版本号
     *
     * 输出示例：
     *   "Composer version 2.6.5 2023-10-06 16:00:00"
     *   "Composer 2.5.1 2023-05-10 ..."
     *
     * @return string|null 例如 "2.6.5"，解析失败返回 null
     */
    private function parseComposerVersion(string $output): ?string
    {
        if (preg_match('/Composer[^\d]*(\d+\.\d+(?:\.\d+)?)/i', $output, $m)) {
            return $m[1];
        }
        return null;
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

        // 记住本次安装用的表前缀，所有 SQL 改写都依赖它
        $prefix = $params['prefix'] ?? 'na_';
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $prefix);
        if ($prefix === '') {
            $prefix = 'na_';
        }
        $this->prefix = $prefix;

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

            // 3. 写入 .env（必须先写，后续 config/database.php 才认得到 prefix）
            $this->writeEnv($params);

            // 4. 执行 install.sql —— 建表 + 自带的 admin/role/permission 演示数据
            $this->runInstallSql($pdo, $params['name']);

            // 5. 执行 menu_init.sql —— 写入所有菜单节点
            $this->runMenuInitSql($pdo);

            // 6. 把菜单绑定到 R_SUPER 角色，让超管登录后看得到菜单
            $this->bindSuperRoleMenus($pdo);

            // 7. 创建/更新初始管理员（密码使用用户填的，覆盖 install.sql 自带的 hash）
            $this->createAdmin($pdo, $params);

            // 8. 写入 install.lock
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
     *
     * 同一份脚本会做两步事：
     *   1. 建表（含 CREATE TABLE）
     *   2. 写入演示数据（INSERT 系统字典、默认 admin/role/permission）
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
                // 替换表前缀：``na_xxx`` -> ``{prefix}xxx``，裸写 `na_xxx` 也覆盖
                $statement = $this->replaceTablePrefix($statement);

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
     * 执行 sql/menu_init.sql
     *
     * menu_init.sql 包含系统所有菜单节点（REPLACE INTO `th_sys_menu`），
     * 不执行它的话超管登录后看不到任何菜单（前端菜单由后端动态接口返回）。
     */
    public function runMenuInitSql(PDO $pdo): void
    {
        if (!is_file($this->menuInitSqlPath)) {
            // 菜单脚本可选：缺失时仅记录告警，不阻断安装
            error_log('[Install] menu_init.sql 不存在，跳过菜单初始化: ' . $this->menuInitSqlPath);
            return;
        }

        $sql = file_get_contents($this->menuInitSqlPath);
        if ($sql === false) {
            throw new \RuntimeException('菜单 SQL 文件读取失败');
        }

        $statements = $this->splitSql($sql);

        foreach ($statements as $statement) {
            $statement = $this->replaceTablePrefix($statement);
            if ($statement === '') {
                continue;
            }

            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // 菜单脚本主要是 REPLACE INTO，重复键错误可忽略
                if (stripos($e->getMessage(), 'Duplicate') === false
                    && stripos($e->getMessage(), 'already exists') === false) {
                    throw new \RuntimeException(
                        '执行菜单 SQL 失败: ' . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * 将所有菜单绑定到 R_SUPER 角色
     *
     * 必须在 menu_init.sql 之后执行；前置 INSERT IGNORE 防止重复。
     */
    public function bindSuperRoleMenus(PDO $pdo): void
    {
        $roleTable = $this->prefix . 'sys_role';
        $menuTable = $this->prefix . 'sys_menu';
        $roleMenuTable = $this->prefix . 'sys_role_menu';

        // 取 R_SUPER 角色 ID
        $stmt = $pdo->prepare("SELECT id FROM `{$roleTable}` WHERE `code` = ? LIMIT 1");
        $stmt->execute(['R_SUPER']);
        $roleId = $stmt->fetchColumn();

        if (!$roleId) {
            // install.sql 没建出 R_SUPER，跳过（幂等）
            return;
        }

        // 取所有未删除的菜单 ID
        $stmt = $pdo->query("SELECT id FROM `{$menuTable}` WHERE `deleted` = 0");
        $menuIds = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        if (empty($menuIds)) {
            return;
        }

        $sql = "INSERT IGNORE INTO `{$roleMenuTable}` (`role_id`, `menu_id`) VALUES (?, ?)";
        $bindStmt = $pdo->prepare($sql);
        foreach ($menuIds as $menuId) {
            $bindStmt->execute([$roleId, $menuId]);
        }
    }

    /**
     * 替换 SQL 中的硬编码 `na_` 前缀为当前安装任务的 prefix
     *
     * 同时处理 `` `na_xxx` ``（反引号包裹）和裸写 `na_xxx` 两种形态，
     * 注意只替换作为表名前缀出现的 `na_`，避免误伤 `na_sys_xxx` 字段值。
     */
    private function replaceTablePrefix(string $statement): string
    {
        // `` `na_xxx` `` -> `` `{prefix}xxx` ``
        $statement = str_replace('`th_', '`' . $this->prefix, $statement);
        // 裸写 th_xxx（前面是空白、逗号、括号或行首，避免误匹配字段值）
        $statement = preg_replace('/(^|[\s,()])th_/i', '$1' . $this->prefix, $statement);
        return $statement;
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

        $adminTable   = $this->prefix . 'sys_admin';
        $roleTable    = $this->prefix . 'sys_role';
        $adminRoleTable = $this->prefix . 'sys_admin_role';

        // 覆盖 install.sql 中默认 admin/system 账户，统一为用户填写的账号
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

        // 确保 admin_role 关联存在
        $roleCheck = $pdo->prepare("SELECT id FROM `{$roleTable}` WHERE `code` = ? LIMIT 1");
        $roleCheck->execute(['R_SUPER']);
        $superRole = $roleCheck->fetchColumn();

        if ($superRole) {
            $assocSql = "INSERT IGNORE INTO `{$adminRoleTable}` (`admin_id`, `role_id`) VALUES (1, ?)";
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
