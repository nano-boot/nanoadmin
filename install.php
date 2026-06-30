<?php
declare(strict_types=1);

namespace Webman\nanoadmin;

/**
 * webman 插件入口：composer require 后被 support\Plugin::install 调用，
 * 将本包业务目录复制到主项目 plugin/nanoadmin/。架构详见 .cursor/rules/webman-nanoadmin-architecture.mdc。
 */
final class Install
{
    public const WEBMAN_PLUGIN = true;

    /** 主项目持久状态目录（install.lock / install.flock），区别于 runtime/ 运行时缓存 */
    private const PROJECT_STORAGE_DIR = 'storage';

    /** 复制到主项目 plugin/nanoadmin/ 的业务目录；源不存在则静默跳过 */
    private const SHIPPED_ITEMS = [
        'app',
        'config',
        'database',
        'sql',
        'api',
    ];

    /** 浏览器可访问的静态资源：[包 public 子目录, 主项目 public 子目录] */
    private const PUBLIC_ITEMS = [
        ['static/css', 'static/css'],
    ];

    public static function install(): void
    {
        self::copyToMainConfig();
        self::copyToPluginDir();
        self::copyToPublic();
        self::ensureProjectStorageDir();
    }

    /** copy_dir 默认不覆盖，保留用户本地修改 */
    public static function update(): void
    {
        self::copyToMainConfig();
        self::copyToPluginDir();
        self::copyToPublic();
        self::ensureProjectStorageDir();
    }

    /**
     * 将插件配置复制到主项目 config/ 目录（让 webman config() 助手可用）。
     * 不覆盖已存在的文件，保留主项目已有的同名配置。
     */
    private static function copyToMainConfig(): void
    {
        $src  = __DIR__;
        $dest = config_path(); // 主项目 config/ 目录

        // 要复制到主项目 config/ 的文件
        $mainConfigFiles = [
            'cache.php',
            'redis.php',
            'think-cache.php',
        ];

        foreach ($mainConfigFiles as $file) {
            $from = $src . DIRECTORY_SEPARATOR . $file;
            $to   = $dest . DIRECTORY_SEPARATOR . $file;

            if (is_file($from) && !file_exists($to)) {
                copy($from, $to);
                echo "Create config/{$file}\n";
            }
        }
    }

    /**
     * 故意不删 storage/，避免误清用户的 install.lock；
     * 完全重置需手动删除 storage/install.lock。
     */
    public static function uninstall(): void
    {
        $dest = base_path() . '/plugin/nanoadmin';
        if (is_dir($dest)) {
            remove_dir($dest);
            echo "Remove plugin/nanoadmin\n";
        }

        $publicDest = self::publicPath();
        foreach (self::PUBLIC_ITEMS as [$src, $dst]) {
            $dir = $publicDest . DIRECTORY_SEPARATOR . $dst;
            if (is_dir($dir)) {
                remove_dir($dir);
                echo "Remove public/{$dst}\n";
            }
        }

        // 卸载主项目 config/ 下的插件配置（保留用户可能已修改过的文件）
        $mainConfigFiles = ['cache.php', 'redis.php', 'think-cache.php'];
        foreach ($mainConfigFiles as $file) {
            $configFile = config_path() . DIRECTORY_SEPARATOR . $file;
            if (is_file($configFile)) {
                unlink($configFile);
                echo "Remove config/{$file}\n";
            }
        }
    }

    private static function ensureProjectStorageDir(): void
    {
        $dir = base_path() . DIRECTORY_SEPARATOR . self::PROJECT_STORAGE_DIR;
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            // 并发场景：另一进程已建好也视为成功
            echo "Warning: cannot create {$dir}, please create it manually with write permission\n";
            return;
        }
        echo "Create " . self::PROJECT_STORAGE_DIR . "/\n";
    }

    private static function copyToPluginDir(): void
    {
        $src  = __DIR__;
        $dest = base_path() . '/plugin/nanoadmin';

        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }

        foreach (self::SHIPPED_ITEMS as $name) {
            $from = $src . DIRECTORY_SEPARATOR . $name;
            $to   = $dest . DIRECTORY_SEPARATOR . $name;

            if (is_dir($from)) {
                copy_dir($from, $to);
                echo "Create plugin/nanoadmin/{$name}\n";
            } elseif (is_file($from) && !file_exists($to)) {
                copy($from, $to);
                echo "Create plugin/nanoadmin/{$name}\n";
            }
        }
    }

    /** 与 copyToPluginDir 不同：保留用户已有的同名文件，不静默覆盖 */
    private static function copyToPublic(): void
    {
        $srcRoot = __DIR__ . DIRECTORY_SEPARATOR . 'public';
        if (!is_dir($srcRoot)) {
            return;
        }

        $publicDest = self::publicPath();

        foreach (self::PUBLIC_ITEMS as [$src, $dst]) {
            $from = $srcRoot . DIRECTORY_SEPARATOR . $src;
            $to   = $publicDest . DIRECTORY_SEPARATOR . $dst;

            if (!is_dir($from)) {
                continue;
            }

            self::copyDirPreserveExisting($from, $to);
            echo "Create public/{$dst}\n";
        }
    }

    /** webman 框架未 bootstrap（composer post-script 阶段）时回退到 base_path()/public */
    private static function publicPath(): string
    {
        if (function_exists('public_path')) {
            return public_path();
        }

        return base_path() . DIRECTORY_SEPARATOR . 'public';
    }

    /** 递归复制目录但跳过已存在的目标文件 */
    private static function copyDirPreserveExisting(string $from, string $to): void
    {
        if (!is_dir($to)) {
            mkdir($to, 0777, true);
        }

        $items = scandir($from);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $from . DIRECTORY_SEPARATOR . $item;
            $dst = $to . DIRECTORY_SEPARATOR . $item;

            if (is_dir($src)) {
                self::copyDirPreserveExisting($src, $dst);
            } elseif (is_file($src) && !file_exists($dst)) {
                copy($src, $dst);
            }
        }
    }
}
