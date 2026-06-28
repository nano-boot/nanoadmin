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
 */
final class Install
{
    /** 让 webman Plugin::install() 识别这是一个 webman 插件 */
    public const WEBMAN_PLUGIN = true;

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
    }

    /**
     * 更新（逻辑同 install；copy_dir 默认不覆盖，保留用户本地修改）
     */
    public static function update(): void
    {
        self::copyToPluginDir();
        self::copyToPublic();
    }

    /**
     * 卸载：删除主项目 plugin/nanoadmin/
     *
     * 注意：为了避免误删，public/static/ 下的资源只删除本插件自带的部分，
     * 不影响主项目已有的其他静态资源。
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