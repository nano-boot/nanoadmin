<?php

namespace plugin\nanoadmin\app\swagger;

use OpenApi\Annotations as OA;
use OpenApi\Annotations\OpenApi;
use OpenApi\Generator;

/**
 * OpenAPI 文档后处理工具
 *
 * 在 route.php 的 modify 回调里调用，自动给文档补充：
 *  1. 读取 x[schema-to-request-body] 自动追加 OA\RequestBody
 *  2. 给所有 operation 注入 401/403 公共响应
 *  3. 自动补全缺失的 summary / tags / responses
 *  4. 设置基础信息 + servers
 *
 * 用法：
 *   'modify' => function (OpenApi $openApi) {
 *       OpenApiModifier::process($openApi, [
 *           'title' => 'The Admin API',
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

    private function __construct() {}

    /**
     * 一站式 modify 入口：依次执行所有增强
     */
    public static function process(OpenApi $openApi, array $info = []): void
    {
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
                    $operation->responses[] = $response;
                }
            }
        }
    }

    /**
     * 设置 OpenAPI 基础信息
     */
    public static function setInfo(
        OpenApi $openApi,
        string $title,
        string $version = '1.0.0',
        string $description = ''
    ): void {
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
        if (empty($operation->responses)) {
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
        $defaultDesc = $opts['default_description'] ?? '操作成功';

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

                // 自动补 responses（仅当完全无 response 时）
                if (empty($operation->responses)) {
                    $response = new OA\Response([
                        'response' => 200,
                        'description' => $defaultDesc,
                    ]);
                    // 手动设置 content，确保正确的结构
                    $response->content = [
                        'application/json' => new OA\MediaType([
                            'mediaType' => 'application/json',
                            'schema' => new OA\Schema(['ref' => ApiResponseDocs::class]),
                        ]),
                    ];
                    $operation->responses = [$response];
                }
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
