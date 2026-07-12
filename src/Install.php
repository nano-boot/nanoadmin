<?php
namespace Webman\nanoadmin;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * 路径映射：源（在包内 src/ 下镜像出安装后的样子）→ 目标（webman 主项目）
     *
     * 源路径相对 __DIR__（也就是包内 src/ 目录）
     * 目标路径相对 base_path()（webman 主项目根）
     *
     * 例如 src/Install.php 调用：
     *   copy_dir(__DIR__ . "/plugin/nanoadmin", base_path() . "/plugin/nanoadmin");
     */
    protected static $pathRelation = [
        'plugin/nanoadmin' => 'plugin/nanoadmin',
    ];

    /**
     * 安装
     * @return void
     */
    public static function install()
    {
        static::installByRelation();
    }

    /**
     * 卸载
     * @return void
     */
    public static function uninstall()
    {
        static::uninstallByRelation();
    }

    /**
     * 更新
     * @return void
     */
    public static function update()
    {
        static::installByRelation();
    }

    /**
     * 按 $pathRelation 复制 src/plugin/<name>/ → 主项目 plugin/<name>/
     * @return void
     */
    public static function installByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            $destPath = base_path() . '/' . $dest;
            if ($pos = strrpos($destPath, '/')) {
                $parentDir = substr($destPath, 0, $pos);
                if (!is_dir($parentDir)) {
                    mkdir($parentDir, 0777, true);
                }
            }
            $sourcePath = __DIR__ . '/' . $source;
            if (!is_dir($sourcePath)) {
                continue;
            }
            copy_dir($sourcePath, $destPath, true);
            echo "Create $dest\n";
        }
    }

    /**
     * 按 $pathRelation 删除主项目 plugin/<name>/
     * @return void
     */
    public static function uninstallByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            $path = base_path() . '/' . $dest;
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            echo "Remove $dest\n";
            if (is_file($path) || is_link($path)) {
                unlink($path);
                continue;
            }
            remove_dir($path);
        }
    }
}