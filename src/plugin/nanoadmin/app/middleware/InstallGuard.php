<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 安装守卫中间件
 *
 * 检测 storage/install.lock 文件：
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

    /**
     * 后续 Auth/Permission 中间件使用的平台路由白名单。
     * 注意：这里包含 /，但 InstallGuard 未安装放行列表只包含 /install。
     */
    private const PLATFORM_ROUTES = [
        '/',
        '/install',
    ];

    /**
     * 平台级白名单路由（供 BaseMiddleware 自动注入）

     *
     * @return string[]
     */
    public static function platformRoutes(): array
    {
        return self::PLATFORM_ROUTES;
    }

    public function process(Request $request, callable $next): Response
    {
        $lock = base_path() . '/storage/install.lock';
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
