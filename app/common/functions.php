<?php

/**
 * 插件公共函数
 */

if (!function_exists('plugin_theadmin_path')) {
    /**
     * 获取 TheAdmin 插件目录路径
     *
     * @param string $path 相对路径
     * @return string
     */
    function plugin_theadmin_path(string $path = ''): string
    {
        static $basePath = null;

        if ($basePath === null) {
            $basePath = dirname(__DIR__, 2);
        }

        return $path ? $basePath . DIRECTORY_SEPARATOR . $path : $basePath;
    }
}

if (!function_exists('theadmin_config')) {
    /**
     * 获取 TheAdmin 插件配置
     *
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    function theadmin_config(string $key = '', $default = null)
    {
        static $config = [];

        if (empty($config)) {
            $configFile = plugin_theadmin_path('config/app.php');
            if (file_exists($configFile)) {
                $config = include $configFile;
            }
        }

        if ($key === '') {
            return $config;
        }

        return $config[$key] ?? $default;
    }
}
