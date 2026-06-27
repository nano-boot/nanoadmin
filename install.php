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
     * 安装
     */
    public static function install(): void
    {
        self::copyToPluginDir();
    }

    /**
     * 更新（逻辑同 install；copy_dir 默认不覆盖，保留用户本地修改）
     */
    public static function update(): void
    {
        self::copyToPluginDir();
    }

    /**
     * 卸载：删除主项目 plugin/nanoadmin/
     */
    public static function uninstall(): void
    {
        $dest = base_path() . '/plugin/nanoadmin';
        if (is_dir($dest)) {
            remove_dir($dest);
            echo "Remove plugin/nanoadmin\n";
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
}