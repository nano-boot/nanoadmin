<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 安装守卫中间件
 *
 * 检测 plugin/nanoadmin/storage/install.lock 文件：
 * - 存在：已安装，放行
 * - 不存在：未安装，除 /install 外全部 302 重定向到 /install
 *
 * 静态资源通过 CDN 内联到向导 HTML，不依赖主项目 public/static/。
 */
class InstallGuard implements MiddlewareInterface
{
    /**
     * 允许未安装状态访问的路径前缀
     */
    private const ALLOW = [
        '/install',
    ];

    public function process(Request $request, callable $next): Response
    {
        $lock = base_path() . '/plugin/nanoadmin/storage/install.lock';

        // 已安装：放行
        if (is_file($lock)) {
            return $next($request);
        }

        $path = '/' . ltrim($request->path(), '/');

        // 白名单：放行
        foreach (self::ALLOW as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $next($request);
            }
        }

        // 未安装：重定向到向导
        return redirect('/install');
    }
}
