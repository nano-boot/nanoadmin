<?php
declare(strict_types=1);

namespace Webman\nanoadmin;

/**
 * webman 插件安装入口（包级，与业务 Install 类 plugin\nanoadmin\api\Install 无关）
 *
 * 触发时机：当用户在 webman 主项目执行 `composer require nano-boot/nanoadmin` 后，
 * 主项目 composer.json 中的 `support\\Plugin::install` 脚本会被触发。
 * webman 框架会通过 psr-4 autoload 找到本类（识别条件：常量 WEBMAN_PLUGIN = true），
 * 调用本类的 install/update/uninstall 方法。
 *
 * 行为：把当前包（仓库根）下的业务目录（app/、config/、database/、sql/、api/）
 * 复制到主项目 plugin/nanoadmin/。webman 的 copy_dir() 默认不覆盖已有文件，
 * 用户本地修改过的配置会被保留。
 *
 * 同时把 static/ 下需要被浏览器直接访问的"前端静态资源"
 * （如安装向导用到的 bootstrap / font-awesome 等第三方 CSS）复制到主项目 public/static/ 下，
 * 这样无需配置额外路由即可通过 /static/... 直接访问这些资源。
 *
 * 安装/更新时还会在主项目根下创建 storage/ 目录，用于存放 install.lock
 * 等业务级持久状态标记（区别于 runtime/ 的运行时缓存）。该目录不会被卸载，
 * 以避免卸载时误删用户的安装状态——重装时 InstallService 会自动复用。
 */
final class Install
{
    /** 让 webman Plugin::install() 识别这是一个 webman 插件 */
    public const WEBMAN_PLUGIN = true;

    /**
     * 主项目根目录下的 storage/ 子目录名。
     *
     * 存放 install.lock（已安装标记）、install.flock（安装并发锁）等
     * 业务级持久状态文件。不放在 runtime/ 是因为 runtime/ 会被用户清缓存误删；
     * 不放在 public/ 是因为 public/ 是 web 文档根，文件会被 HTTP 访问到；
     * 不放在 plugin/nanoadmin/storage/ 是因为该目录不在 SHIPPED_ITEMS 白名单，
     * 不会被复制到主项目，且会污染插件包源码树。
     */
    private const PROJECT_STORAGE_DIR = 'storage';

    /**
     * 要复制到主项目 plugin/nanoadmin/ 下的业务目录。
     * 注意：composer.json、README.md、.git*、.phpunit.cache 等仓库元数据不在此列表。
     * 源代码不存在的目录会被静默跳过，所以将来加新目录只需在仓库里创建并追加到这里。
     */
    private const SHIPPED_ITEMS = [
        'app',
        'config',
        'database',
        'sql',
        'api',
    ];

    /**
     * 浏览器可访问的静态资源。源在包根 public/{src}，会被复制到主项目 public/{dst}。
     * webman 默认会把 public/ 下的文件按路径直接暴露，所以 /{dst}/foo.css
     * 会自动对应到 public/{dst}/foo.css，无需写路由。
     *
     * 数组项格式：['src 子目录名', 'dst 子目录名（位于主项目 public/ 下）']。
     */
    private const PUBLIC_ITEMS = [
        ['static/css', 'static/css'],
    ];

    /**
     * 安装
     */
    public static function install(): void
    {
        self::copyToPluginDir();
        self::copyToPublic();
        self::ensureProjectStorageDir();
    }

    /**
     * 更新（逻辑同 install；copy_dir 默认不覆盖，保留用户本地修改）
     */
    public static function update(): void
    {
        self::copyToPluginDir();
        self::copyToPublic();
        self::ensureProjectStorageDir();
    }

    /**
     * 卸载：删除主项目 plugin/nanoadmin/
     *
     * 注意 1：为了避免误删，public/static/ 下的资源只删除本插件自带的部分，
     *         不影响主项目已有的其他静态资源。
     *
     * 注意 2：主项目根下的 storage/ 目录故意**不删除**。
     *         该目录下的 install.lock 是"已安装"标记，卸载插件不应清空用户的安装状态；
     *         下次重装时 InstallService 检测到 lock 文件存在会拒绝再次执行 SQL。
     *         若用户确实想"完全重置"，应手动删除 storage/install.lock。
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
    }

    /**
     * 确保主项目根下的 storage/ 目录存在。
     *
     * 用于存放 install.lock（已安装标记）和 install.flock（安装并发锁）。
     * 在 install/update 时建好，避免业务运行时懒创建失败（典型如 open_basedir 限制
     * 或父目录不可写的场景）。该目录与 plugin/nanoadmin/ 解耦，跨 install/update 复用。
     */
    private static function ensureProjectStorageDir(): void
    {
        $dir = base_path() . DIRECTORY_SEPARATOR . self::PROJECT_STORAGE_DIR;
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            // mkdir 失败但其他进程已建好也视为成功（并发场景），否则真失败
            echo "Warning: cannot create {$dir}, please create it manually with write permission\n";
            return;
        }
        echo "Create " . self::PROJECT_STORAGE_DIR . "/\n";
    }

    /**
     * 把当前包根下的业务目录复制到 主项目 plugin/nanoadmin/
     */
    private static function copyToPluginDir(): void
    {
        $src  = __DIR__;                           // 包根 = Install.php 所在目录
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
            } elseif (is_file($from)) {
                if (!file_exists($to)) {
                    copy($from, $to);
                    echo "Create plugin/nanoadmin/{$name}\n";
                }
            }
            // 源不存在则静默跳过
        }
    }

    /**
     * 把包根 public/ 下的"前端静态资源"复制到主项目 public/ 下的对应路径。
     *
     * 例：包根 public/css/bootstrap.min.css
     *   → 主项目 public/static/css/bootstrap.min.css
     * 这样浏览器就能通过 /static/css/bootstrap.min.css 直接访问。
     *
     * 与 copyToPluginDir 的策略不同：为了避免覆盖用户自定义的同名 CSS，
     * 这里用 file_exists 跳过已存在的目标文件；同时不删除用户已有的目录。
     */
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
                continue; // 源不存在静默跳过
            }

            self::copyDirPreserveExisting($from, $to);
            echo "Create public/{$dst}\n";
        }
    }

    /**
     * 解析主项目 public/ 绝对路径。
     *
     * 优先用 webman 的 public_path() 辅助函数（标准做法）；
     * 如果 webman 框架尚未 bootstrap（Install 由 composer post-script 触发时就是这种状态），
     * 则回退到 base_path()/public（与 config/app.php::public_path 等价）。
     */
    private static function publicPath(): string
    {
        if (function_exists('public_path')) {
            return public_path();
        }

        return base_path() . DIRECTORY_SEPARATOR . 'public';
    }

    /**
     * 递归复制目录，但跳过已存在的目标文件（不覆盖）。
     * 用于静态资源场景：用户可能已经在主项目 public/static/ 下做了定制，
     * 我们不应该静默覆盖。
     */
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
            } elseif (is_file($src)) {
                if (!file_exists($dst)) {
                    copy($src, $dst);
                }
            }
        }
    }
}