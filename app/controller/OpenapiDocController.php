<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use OpenApi\Util;
use plugin\nanoadmin\app\swagger\OpenApiModifier;
use plugin\nanoadmin\app\swagger\Processors\InjectDefault200Response;
use plugin\nanoadmin\app\swagger\Processors\SchemaQueryParameter;
use support\Response;

/**
 * 自定义 OpenAPI doc 路由 handler
 *
 * 完整基于 zircote/swagger-php 实现，不依赖 webman-tech/swagger：
 *  - swagger-php 自带的 ReflectionAnalyser（PHP 8 attribute 解析）做注解扫描
 *  - SchemaQueryParameter（自维护） 把 x[schema-to-parameters] 展开成 query parameters
 *  - InjectDefault200Response（自维护） 在 validate() 之前给未声明响应的 operation 补 200 响应
 *  - OpenApiModifier（自维护） 在 validate() 之后注入 401/403、补 tags/summary/description、设置 Info/Servers
 *
 * 历史：
 *   早期用 webman-tech/swagger 的 OpenapiController，后来发现它不暴露 addProcessor 扩展点，
 *   无法解决 "@OA\\Post() requires at least one @OA\\Response()" 报错，索性直接基于 zircote 写。
 */
class OpenapiDocController
{
    /** doc YAML 输出缓存 */
    private static array $docCache = [];

    /**
     * 输出 OpenAPI YAML 文档
     *
     * @param array $config
     *  - scan_path: string[]
     *  - scan_exclude: string[]|null
     *  - info: array{title:string, version:string, description:string}
     *  - servers: array<int,array{url:string, description?:string}>
     *  - auto_complete: array{default_tag?:string}
     *  - cache_key: string|null
     */
    public function openapiDoc(array $config): Response
    {
        $cacheKey = $config['cache_key'] ?? $this->buildCacheKey($config);

        if (!isset(self::$docCache[$cacheKey])) {
            $openapi = $this->scanAndGenerateOpenapi(
                $config['scan_path'] ?? [],
                $config['scan_exclude'] ?? null
            );

            OpenApiModifier::process($openapi, [
                'title' => $config['info']['title'] ?? 'API',
                'version' => $config['info']['version'] ?? '1.0.0',
                'description' => $config['info']['description'] ?? '',
                'servers' => $config['servers'] ?? [],
                'auto_complete' => $config['auto_complete'] ?? [],
            ]);

            self::$docCache[$cacheKey] = $openapi->toYaml();
        }

        return response(self::$docCache[$cacheKey], 200, [
            'Content-Type' => 'application/x-yaml',
        ]);
    }

    private function scanAndGenerateOpenapi(array $scanPath, ?array $scanExclude = null): OA\OpenApi
    {
        return (new Generator())
            ->setAliases(Generator::DEFAULT_ALIASES)
            ->setNamespaces(Generator::DEFAULT_NAMESPACES)
            ->setAnalyser(new ReflectionAnalyser([new AttributeAnnotationFactory()]))
            ->addProcessor(new SchemaQueryParameter())     // x[schema-to-parameters] -> query 参数
            ->addProcessor(new InjectDefault200Response()) // 关键：在 validate 之前补 200 响应
            ->generate(Util::finder($scanPath, $scanExclude));
    }

    private function buildCacheKey(array $config): string
    {
        return md5(json_encode([
            'scan_path' => $config['scan_path'] ?? [],
            'scan_exclude' => $config['scan_exclude'] ?? null,
        ]));
    }
}
