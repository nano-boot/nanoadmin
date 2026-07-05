<?php

namespace plugin\nanoadmin\app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 跨域中间件
 *
 * 默认行为：透传 Origin / Allow-Methods / Allow-Headers，
 *          以兼容大多数本地调试场景。
 *
 * 通过主项目 config('plugin.nanoadmin.nanoadmin.cors') 或 .env 覆盖：
 *   - allow_origin       '*' 或具体域名数组
 *   - allow_methods      GET,POST,...
 *   - allow_headers      Content-Type,...
 *   - allow_credentials  'true' / 'false'
 *   - max_age            预检缓存秒数
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * 解析后的配置缓存
     * @var array|null
     */
    protected static ?array $cachedConfig = null;

    public function __construct()
    {
        if (self::$cachedConfig === null) {
            $config = function_exists('config') ? config('plugin.nanoadmin.nanoadmin.cors', []) : [];
            self::$cachedConfig = is_array($config) ? $config : [];
        }
    }

    public function process(Request $request, callable $handler): Response
    {
        // OPTIONS 预检直接返回 204
        $response = $request->method() === 'OPTIONS'
            ? response('', 204)
            : $handler($request);

        $origin = $request->header('origin', '*');
        $allowOrigin = $this->resolveAllowOrigin($origin);

        $response->withHeaders([
            'Access-Control-Allow-Origin'      => $allowOrigin,
            'Access-Control-Allow-Methods'     => self::$cachedConfig['allow_methods']
                ?? 'GET,POST,PUT,DELETE,OPTIONS,PATCH',
            'Access-Control-Allow-Headers'     => self::$cachedConfig['allow_headers']
                ?? 'Content-Type,Authorization,X-Requested-With',
            'Access-Control-Allow-Credentials' => self::$cachedConfig['allow_credentials'] ?? 'true',
            'Access-Control-Max-Age'           => (string) (self::$cachedConfig['max_age'] ?? 86400),
            // 当 allow_origin 不是通配符时，Vary: Origin 是必备
            'Vary'                             => $allowOrigin === '*' ? '' : 'Origin',
        ]);

        return $response;
    }

    /**
     * 解析允许的来源
     */
    protected function resolveAllowOrigin(string $origin): string
    {
        $configured = self::$cachedConfig['allow_origin'] ?? '*';

        if ($configured === '*') {
            return $origin !== '' ? $origin : '*';
        }

        if (is_array($configured) && in_array($origin, $configured, true)) {
            return $origin;
        }

        // 不在白名单内：回退到第一个白名单值，保证响应头始终合法
        return is_array($configured) ? ($configured[0] ?? '*') : (string) $configured;
    }
}