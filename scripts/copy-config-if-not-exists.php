<?php

/**
 * NanoAdmin 配置复制脚本
 *
 * 功能：只复制主项目不存在的配置文件，保留用户已有的配置
 *
 * 使用方式：
 *   php scripts/copy-config-if-not-exists.php
 *
 * 此脚本会在 composer install/update 时自动调用
 */

// 防止直接访问
if (php_sapi_name() !== 'cli') {
    die('此脚本只能在命令行环境中运行');
}

// 清除 PHP 文件状态缓存，确保 file_exists 结果准确
clearstatcache(true);

// 获取插件根目录（scripts 的父目录）
$pluginRoot = dirname(__DIR__);

// 主项目根目录（plugin 的上一级）
$projectRoot = dirname(dirname($pluginRoot));

// 需要复制的配置文件列表（相对于插件根目录）
$configFiles = [
    'config/plugin/webman/validation/app.php',
    'config/plugin/webman/validation/middleware.php',
    'config/plugin/webman/validation/command.php',
];

echo "=== NanoAdmin 配置检查 ===\n\n";

$copiedCount = 0;
$skippedCount = 0;

foreach ($configFiles as $configFile) {
    $sourcePath = $pluginRoot . '/' . $configFile;
    $targetPath = $projectRoot . '/' . $configFile;

    // 检查源文件是否存在
    if (!file_exists($sourcePath)) {
        echo "[跳过] 源文件不存在: {$configFile}\n";
        continue;
    }

    // 检查目标文件是否已存在
    if (file_exists($targetPath)) {
        echo "[保留] 主项目已有配置: {$configFile}\n";
        $skippedCount++;
        continue;
    }

    // 确保目标目录存在
    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            echo "[错误] 无法创建目录: {$targetDir}\n";
            continue;
        }
    }

    // 复制文件
    if (copy($sourcePath, $targetPath)) {
        echo "[复制] 已复制配置: {$configFile}\n";
        $copiedCount++;
    } else {
        echo "[错误] 复制失败: {$configFile}\n";
    }
}

echo "\n=== 完成 ===\n";
echo "复制: {$copiedCount} 个文件\n";
echo "保留: {$skippedCount} 个文件（主项目已有）\n";
