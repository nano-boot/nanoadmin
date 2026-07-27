<?php

namespace plugin\nanoadmin\app\common;

use support\response\FileResponse;

/**
 * 代码压缩工具 - 使用 PHP 内置 ZipArchive 生成 ZIP 文件
 */
class CodeZip
{
    /**
     * 生成 ZIP 文件并返回响应
     * @param string $sourceDir 源目录
     * @param string $zipFileName ZIP 文件名（不含扩展名）
     * @return FileResponse
     */
    public static function createZip(string $sourceDir, string $zipFileName): FileResponse
    {
        $zipFileName = $zipFileName . '.zip';
        $tempZipPath = runtime_path() . '/temp/' . $zipFileName;

        if (!is_dir(dirname($tempZipPath))) {
            mkdir(dirname($tempZipPath), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("无法创建 ZIP 文件: $tempZipPath");
        }

        self::addDirectoryToZip($zip, $sourceDir, '');

        $zip->close();

        return new FileResponse($tempZipPath, null, $zipFileName);
    }

    /**
     * 递归添加目录到 ZIP
     * @param \ZipArchive $zip
     * @param string $sourceDir
     * @param string $relativePath
     */
    private static function addDirectoryToZip(\ZipArchive $zip, string $sourceDir, string $relativePath): void
    {
        $files = scandir($sourceDir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $sourceDir . '/' . $file;
            $fileRelativePath = $relativePath ? $relativePath . '/' . $file : $file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($fileRelativePath);
                self::addDirectoryToZip($zip, $filePath, $fileRelativePath);
            } else {
                $zip->addFile($filePath, $fileRelativePath);
            }
        }
    }

    /**
     * 删除临时目录
     * @param string $dir
     */
    public static function cleanTemp(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                self::cleanTemp($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
