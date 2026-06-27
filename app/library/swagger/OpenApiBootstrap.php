<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\library\swagger;

use Webman\Route;

/**
 * OpenAPI / Swagger 路由引导器
 *
 * 一站式注册：
 *  1. 扫描控制器目录的 OA 注解，自动注册为 webman 路由
 *  2. 注册 swagger UI      HTML 页面（路径在配置 swagger.php ui_route）
 *  3. 注册 OpenAPI YAML    文档接口（路径在配置 swagger.php doc_route）
 *
 * 全部配置项集中在 plugin/nanoadmin/config/swagger.php，register() 不再需要传参。
 *
 * 使用方式（route.php 底部一行调用即可）：
 *   \plugin\nanoadmin\app\library\swagger\OpenApiBootstrap::register([
 *       base_path() . '/plugin/nanoadmin/app/controller',
 *   ]);
 *
 * 默认扫描路径（递归包含子目录）：
 *  - 控制器目录（业务方传入）
 *  - library/swagger（基础设施 + 通用 schema）
 *  - schema/（业务 schema，与 controller 同级）
 *  - 配置 swagger.php 中 scan_path 额外追加的路径
 *
 * 关闭文档：在 plugin/nanoadmin/config/swagger.php 中把 enabled 设为 false，
 * 此时本引导器完全不注册任何路由。
 */
final class OpenApiBootstrap
{
    /** @var string swagger UI HTML 模板 */
    private const SWAGGER_UI_TEMPLATE = __DIR__ . '/templates/swagger-ui.html';

    /** @var string swagger UI 静态资源目录 */
    private const SWAGGER_UI_ASSETS_DIR = __DIR__ . '/templates/assets';

    /**
     * 一键注册 OpenAPI 路由
     *
     * 所有可配置项均从 plugin/nanoadmin/config/swagger.php 读取（key 为 plugin.nanoadmin.swagger）。
     * 如需在调用处临时覆盖，可通过第二个参数传入（仅覆盖传入的字段，未传入字段仍以配置为准）。
     *
     * @param array $controllerPaths 要扫描的控制器目录列表（递归），用于注册 OA 注解路由
     * @param array $override 临时覆盖（可选）；支持的 key 与 swagger.php 完全一致
     *  - enabled: bool
     *  - ui_route: string swagger UI 路径
     *  - doc_route: string OpenAPI YAML 路径
     *  - scan_path: string[] 额外扫描路径（与默认扫描路径合并）
     *  - info: { title, version, description }
     *  - servers: [{ url, description }]
     *  - auto_complete: { default_tag }
     */
    public static function register(array $controllerPaths, array $override = []): void
    {
        // 0. 合并配置：默认配置 + 调用方 override
        $config = self::loadConfig();
        $config = array_replace_recursive($config, $override);

        // 1. 关闭开关：直接返回，不注册任何路由
        if (empty($config['enabled'])) {
            return;
        }

        // 2. 扫描并注册控制器 OA 注解路由
        (new OpenApiRouteRegister())->register($controllerPaths);

        // 3. 准备文档配置
        $defaultLibraryPath = dirname(__DIR__, 2) . '/library/swagger';
        $defaultSchemaPath = dirname(__DIR__, 2) . '/schema';
        $docConfig = self::buildDocConfig($controllerPaths, $defaultLibraryPath, $defaultSchemaPath, $config);

        // 4. 注册 swagger UI 页面
        $uiRoute = $config['ui_route'];
        $docRoute = $config['doc_route'];
        Route::get($uiRoute, function () use ($uiRoute, $docRoute) {
            // 由后端注入 UI 根路径与静态资源绝对 URL，避免 <base href> 引起的 deepLinking 路径偏移
            $html = (string) file_get_contents(self::SWAGGER_UI_TEMPLATE);
            $html = strtr($html, [
                '{{UI_PATH}}' => $uiRoute,
                '{{CSS_URL}}' => $uiRoute . '/assets/swagger-ui.css',
                '{{JS_URL}}' => $uiRoute . '/assets/swagger-ui-bundle.js',
                '{{DOC_URL}}' => $docRoute,
            ]);
            return new \support\Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $html
            );
        });

        // 4.1 注册 swagger UI 静态资源（CSS / JS 等），避免依赖 CDN
        $assetBase = '/' . trim($uiRoute, '/') . '/assets';
        Route::get($assetBase . '/{file}', function (string $file) {
            $file = basename($file);
            $path = self::SWAGGER_UI_ASSETS_DIR . '/' . $file;
            if (!is_file($path)) {
                return new \support\Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'css' => 'text/css; charset=utf-8',
                'js' => 'application/javascript; charset=utf-8',
                'png' => 'image/png',
                'svg' => 'image/svg+xml',
                default => 'application/octet-stream',
            };
            return new \support\Response(200, ['Content-Type' => $mime], (string) file_get_contents($path));
        });

        // 5. 注册 OpenAPI YAML 文档接口
        $generator = new OpenApiDocGenerator();
        Route::get($docRoute, function () use ($generator, $docConfig) {
            return $generator->toResponse($docConfig);
        });
    }

    /**
     * 读取 plugin/nanoadmin/config/swagger.php，并填上默认值
     */
    private static function loadConfig(): array
    {
        $defaults = [
            'enabled' => true,
            'ui_route' => '/sys/openapi',
            'doc_route' => '/sys/openapi/doc',
            'info' => [
                'title' => 'Nano Admin API',
                'version' => '1.0.0',
                'description' => 'Nano Admin 后台管理系统 API 文档',
            ],
            'servers' => [
                ['url' => '/', 'description' => '当前服务'],
            ],
            'auto_complete' => [
                'default_tag' => '其他',
            ],
            'scan_path' => [],
        ];

        $fileConfig = [];
        if (function_exists('config')) {
            $fileConfig = (array) config('plugin.nanoadmin.swagger', []);
        }

        // array_replace_recursive：fileConfig 同 key 覆盖 defaults，但 fileConfig 中数组不会被 defaults 的同名数组整段替换
        return array_replace_recursive($defaults, $fileConfig);
    }

    /**
     * 构建 OpenAPI 文档配置（合并默认扫描路径 + 用户配置 + override）
     */
    private static function buildDocConfig(array $controllerPaths, string $defaultLibraryPath, string $defaultSchemaPath, array $config): array
    {
        $extraScanPath = is_array($config['scan_path'] ?? null) ? $config['scan_path'] : [];
        $scanPath = array_values(array_unique(array_filter(array_merge(
            $controllerPaths,
            [$defaultLibraryPath],
            [$defaultSchemaPath],
            $extraScanPath
        ))));

        return [
            'scan_path' => $scanPath,
            'info' => $config['info'] ?? [
                'title' => 'Nano Admin API',
                'version' => '1.0.0',
                'description' => 'Nano Admin 后台管理系统 API 文档',
            ],
            'servers' => $config['servers'] ?? [
                ['url' => '/', 'description' => '当前服务'],
            ],
            'auto_complete' => $config['auto_complete'] ?? [
                'default_tag' => '其他',
            ],
        ];
    }
}