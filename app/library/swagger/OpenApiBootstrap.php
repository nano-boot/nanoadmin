<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\library\swagger;

use Webman\Route;

/**
 * OpenAPI / Swagger 路由引导器
 *
 * 一站式注册：
 *  1. 扫描控制器目录的 OA 注解，自动注册为 webman 路由
 *  2. 注册 /sys/openapi      swagger UI HTML 页面
 *  3. 注册 /sys/openapi/doc  OpenAPI YAML 文档接口
 *
 * 使用方式（route.php 底部一行调用即可）：
 *   \plugin\nanoadmin\app\library\swagger\OpenApiBootstrap::register(
 *       controllerPaths: [base_path() . '/plugin/nanoadmin/app/controller'],
 *   );
 *
 * 默认扫描路径（递归包含子目录）：
 *  - 控制器目录（业务方传入）
 *  - library/swagger（基础设施 + 通用 schema）
 *  - schema/（业务 schema，与 controller 同级）
 *
 * 业务方如需自定义 OpenAPI info / servers / 扫描路径，可传入 config 覆盖。
 */
final class OpenApiBootstrap
{
    /** @var string swagger UI HTML 路径 */
    private const SWAGGER_UI_TEMPLATE = __DIR__ . '/templates/swagger-ui.html';

    /**
     * 一键注册 OpenAPI 路由
     *
     * @param array $controllerPaths 要扫描的控制器目录列表（递归），用于注册 OA 注解路由
     * @param array $config OpenAPI 文档配置（覆盖默认）
     *  - scan_path: string[] 扫描路径（默认 = $controllerPaths + library/swagger）
     *  - info: { title, version, description }
     *  - servers: [{ url, description }]
     *  - auto_complete: { default_tag }
     *  - ui_route: string swagger UI 路径，默认 /sys/openapi
     *  - doc_route: string OpenAPI YAML 路径，默认 /sys/openapi/doc
     */
    public static function register(array $controllerPaths, array $config = []): void
    {
        // 1. 扫描并注册控制器 OA 注解路由
        (new OpenApiRouteRegister())->register($controllerPaths);

        // 2. 准备文档配置
        $defaultLibraryPath = dirname(__DIR__, 2) . '/library/swagger';
        $defaultSchemaPath = dirname(__DIR__, 2) . '/schema';
        $docConfig = self::buildDocConfig($controllerPaths, $defaultLibraryPath, $defaultSchemaPath, $config);

        // 3. 注册 swagger UI 页面
        $uiRoute = $config['ui_route'] ?? '/sys/openapi';
        Route::get($uiRoute, function () {
            return new \support\Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                (string) file_get_contents(self::SWAGGER_UI_TEMPLATE)
            );
        });

        // 4. 注册 OpenAPI YAML 文档接口
        $docRoute = $config['doc_route'] ?? '/sys/openapi/doc';
        $generator = new OpenApiDocGenerator();
        Route::get($docRoute, function () use ($generator, $docConfig) {
            return $generator->toResponse($docConfig);
        });
    }

    /**
     * 构建 OpenAPI 文档配置（合并用户传入 + 默认值）
     */
    private static function buildDocConfig(array $controllerPaths, string $defaultLibraryPath, string $defaultSchemaPath, array $config): array
    {
        $scanPath = $config['scan_path']
            ?? array_values(array_unique(array_merge(
                $controllerPaths,
                [$defaultLibraryPath],
                [$defaultSchemaPath]
            )));

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
