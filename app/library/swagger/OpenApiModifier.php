<?php

namespace plugin\nanoadmin\app\library\swagger;

use OpenApi\Annotations as OA;
use OpenApi\Annotations\OpenApi;
use OpenApi\Generator;
use plugin\nanoadmin\app\library\swagger\ApiResponseDocs;

/**
 * OpenAPI 文档后处理工具
 *
 * 在 route.php 的 modify 回调里调用，自动给文档补充：
 *  1. 读取 x[schema-to-request-body] 自动追加 OA\RequestBody
 *  2. 读取 x[schema-to-path-parameters] 自动追加 OA\Parameter（路径参数）
 *  3. 给所有 operation 注入 401/403 公共响应
 *  4. 自动补全缺失的 summary / tags / responses
 *  5. 设置基础信息 + servers
 *
 * 用法：
 *   'modify' => function (OpenApi $openApi) {
 *       OpenApiModifier::process($openApi, [
 *           'title' => 'Nano Admin API',
 *           'version' => '1.0.0',
 *           'description' => '...',
 *           'servers' => [['url' => '/']],
 *           'auto_complete' => [
 *               'default_tag' => '接口',   // 无 Tag 的 operation 默认 tag
 *           ],
 *       ]);
 *   }
 */
final class OpenApiModifier
{
    /**
     * 要注入的公共 HTTP 状态码
     */
    public const COMMON_RESPONSES = [401, 403];

    /**
     * RequestBody 自动注入的 x key
     */
    public const X_REQUEST_BODY = 'schema-to-request-body';

    /**
     * 路径参数自动注入的 x key
     *
     * 控制器写法：
     *   #[OA\Get(
     *       path: '/sys/admin/{id}',
     *       x: [OpenApiModifier::X_PATH_PARAMETERS => [
     *           'id' => ['type' => 'integer', 'description' => '管理员ID'],
     *       ]]
     *   )]
     */
    public const X_PATH_PARAMETERS = 'schema-to-path-parameters';

    private function __construct() {}

    /**
     * 一站式 modify 入口：依次执行所有增强
     */
    public static function process(OpenApi $openApi, array $info = []): void
    {
        self::injectPathParameters($openApi);
        self::injectRequestBodies($openApi);
        self::injectCommonResponses($openApi);
        self::injectAutoComplete($openApi, $info['auto_complete'] ?? []);
        self::setInfo(
            $openApi,
            $info['title'] ?? 'API',
            $info['version'] ?? '1.0.0',
            $info['description'] ?? ''
        );
        if (!empty($info['servers'])) {
            self::setServers($openApi, $info['servers']);
        }
    }

    /**
     * 根据 operation 的 x[X_PATH_PARAMETERS] 自动追加 OA\Parameter
     *
     * 控制器写法：
     *   #[OA\Get(
     *       path: '/sys/admin/{id}',
     *       x: [OpenApiModifier::X_PATH_PARAMETERS => [
     *           'id' => ['type' => 'integer', 'description' => '管理员ID'],
     *       ]]
     *   )]
     */
    public static function injectPathParameters(OpenApi $openApi): void
    {
        if (empty($openApi->paths)) {
            return;
        }

        $methodAttrs = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];

        foreach ($openApi->paths as $path) {
            foreach ($methodAttrs as $attr) {
                $operation = $path->{$attr} ?? null;
                if (!$operation instanceof OA\Operation) {
                    continue;
                }

                if (Generator::isDefault($operation->x) || !array_key_exists(self::X_PATH_PARAMETERS, $operation->x)) {
                    continue;
                }

                $paramsConfig = $operation->x[self::X_PATH_PARAMETERS];
                if (!is_array($paramsConfig)) {
                    continue;
                }

                foreach ($paramsConfig as $name => $config) {
                    // 已手动声明的同名参数，跳过
                    if (self::hasPathParameter($operation, $name)) {
                        continue;
                    }

                    $parameter = new OA\Parameter([
                        'name' => $name,
                        'in' => 'path',
                        'required' => true,
                        'schema' => new OA\Schema([
                            'type' => $config['type'] ?? 'string',
                        ]),
                        'description' => $config['description'] ?? '',
                    ]);
                    if (Generator::isDefault($operation->parameters) || !is_array($operation->parameters)) {
                        $operation->parameters = [];
                    }
                    $operation->parameters[] = $parameter;
                }

                self::cleanX($operation, self::X_PATH_PARAMETERS);
            }
        }
    }

    private static function hasPathParameter(OA\Operation $operation, string $name): bool
    {
        if (Generator::isDefault($operation->parameters) || !is_array($operation->parameters)) {
            return false;
        }
        foreach ($operation->parameters as $p) {
            if ($p->parameter === $name && $p->in === 'path') {
                return true;
            }
        }
        return false;
    }

    /**
     * 根据 operation 的 x[X_REQUEST_BODY] 自动追加 OA\RequestBody
     *
     * 控制器写法：
     *   #[OA\Post(
     *       path: '/sys/admin',
     *       x: [OpenApiModifier::X_REQUEST_BODY => AdminCreateRequest::class]
     *   )]
     */
    public static function injectRequestBodies(OpenApi $openApi): void
    {
        if (empty($openApi->paths)) {
            return;
        }

        $methodAttrs = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];

        foreach ($openApi->paths as $path) {
            foreach ($methodAttrs as $attr) {
                $operation = $path->{$attr} ?? null;
                if (!$operation instanceof OA\Operation) {
                    continue;
                }

                if (Generator::isDefault($operation->x) || !array_key_exists(self::X_REQUEST_BODY, $operation->x)) {
                    continue;
                }

                // 已有手动声明的 requestBody，跳过
                if (!Generator::isDefault($operation->requestBody)) {
                    self::cleanX($operation, self::X_REQUEST_BODY);
                    continue;
                }

                $schemaClass = $operation->x[self::X_REQUEST_BODY];
                if (!is_string($schemaClass)) {
                    self::cleanX($operation, self::X_REQUEST_BODY);
                    continue;
                }

                $requestBody = new OA\RequestBody([
                    'required' => true,
                    'content' => [
                        'application/json' => new OA\MediaType([
                            'mediaType' => 'application/json',
                            'schema' => new OA\Schema(['ref' => $schemaClass]),
                        ]),
                    ],
                ]);
                $operation->requestBody = $requestBody;

                self::cleanX($operation, self::X_REQUEST_BODY);
            }
        }
    }

    /**
     * 给所有 operation 注入公共 401/403 响应
     */
    public static function injectCommonResponses(OpenApi $openApi): void
    {
        if (empty($openApi->paths)) {
            return;
        }

        $methodAttrs = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];

        foreach ($openApi->paths as $path) {
            foreach ($methodAttrs as $attr) {
                $operation = $path->{$attr} ?? null;
                if (!$operation instanceof OA\Operation) {
                    continue;
                }

                foreach (self::COMMON_RESPONSES as $status) {
                    if (self::hasResponse($operation, $status)) {
                        continue;
                    }
                    $response = new OA\Response([
                        'response' => $status,
                        'description' => self::getDescription($status),
                    ]);
                    // 手动设置 content，确保正确的结构
                    $response->content = [
                        'application/json' => new OA\MediaType([
                            'mediaType' => 'application/json',
                            'schema' => new OA\Schema(['ref' => ApiResponseDocs::class]),
                        ]),
                    ];
                    if (Generator::isDefault($operation->responses) || !is_array($operation->responses)) {
                        $operation->responses = [];
                    }
                    $operation->responses[] = $response;
                }
            }
        }
    }

    /**
     * 设置 OpenAPI 基础信息
     *
     * 如果扫描阶段没找到 #[OA\Info] 占位类，这里会兜底创建一个空 Info，
     * 避免 $openApi->info->title 这种写法在 UNDEFINED 上报错。
     */
    public static function setInfo(
        OpenApi $openApi,
        string $title,
        string $version = '1.0.0',
        string $description = ''
    ): void {
        if (Generator::isDefault($openApi->info)) {
            $openApi->info = new OA\Info([
                '_context' => $openApi->_context,
            ]);
        }
        $openApi->info->title = $title;
        $openApi->info->version = $version;
        if ($description !== '') {
            $openApi->info->description = $description;
        }
    }

    /**
     * 设置 servers
     *
     * @param array<int, array{url: string, description?: string}> $servers
     */
    public static function setServers(OpenApi $openApi, array $servers): void
    {
        $openApi->servers = array_map(
            fn($s) => new OA\Server(['url' => $s['url'], 'description' => $s['description'] ?? '']),
            $servers
        );
    }

    private static function hasResponse(OA\Operation $operation, int $status): bool
    {
        if (Generator::isDefault($operation->responses) || !is_array($operation->responses)) {
            return false;
        }
        foreach ($operation->responses as $r) {
            if ($r->response == $status) {
                return true;
            }
        }
        return false;
    }

    /**
     * 自动补全缺失的 summary / tags / responses
     *
     * @param array{default_tag?: string, default_description?: string} $opts
     */
    private static function injectAutoComplete(OpenApi $openApi, array $opts = []): void
    {
        if (empty($openApi->paths)) {
            return;
        }

        $defaultTag = $opts['default_tag'] ?? '接口';

        $methodAttrs = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];
        $actionNames = [
            'page' => '分页列表',
            'show' => '获取详情',
            'create' => '创建',
            'update' => '更新',
            'destroy' => '删除',
            'batchDestroy' => '批量删除',
        ];

        foreach ($openApi->paths as $path) {
            foreach ($methodAttrs as $attr) {
                $operation = $path->{$attr} ?? null;
                if (!$operation instanceof OA\Operation) {
                    continue;
                }

                // 自动补 summary（从方法名推断）
                if (Generator::isDefault($operation->summary) && isset($operation->_context->method)) {
                    $methodName = $operation->_context->method;
                    $operation->summary = $actionNames[$methodName] ?? $methodName;
                }

                // 自动补 tags
                if (Generator::isDefault($operation->tags) || empty($operation->tags)) {
                    // 优先取 controller 级别注入的 tag
                    $className = $operation->_context->class ?? '';
                    if ($className) {
                        $parts = explode('\\', $className);
                        $shortName = end($parts);
                        $tag = preg_replace('/Controller$/', '', $shortName);
                        $operation->tags = [$tag];
                    } else {
                        $operation->tags = [$defaultTag];
                    }
                }

                // 200 响应兜底已移到 Processor 阶段（InjectDefault200Response），
                // 在 swagger-php validate() 之前完成。这里不再重复注入。
            }
        }
    }

    private static function cleanX(OA\Operation $operation, string $key): void
    {
        unset($operation->x[$key]);
        if (!$operation->x) {
            $operation->x = Generator::UNDEFINED;
        }
    }

    private static function getDescription(int $status): string
    {
        return match ($status) {
            401 => '未授权',
            403 => '权限不足',
            404 => '资源不存在',
            500 => '服务器错误',
            default => "HTTP $status",
        };
    }
}
