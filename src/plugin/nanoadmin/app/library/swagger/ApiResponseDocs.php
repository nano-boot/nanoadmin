<?php

namespace plugin\nanoadmin\app\library\swagger;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ApiResponse;
use plugin\nanoadmin\app\library\swagger\PagedResponse;

/**
 * OpenAPI 响应注解工厂（运行时使用）
 *
 * PHP 8 注解参数必须是常量表达式，不允许调用方法，所以这些工厂方法
 * 只能用于以下场景：
 *  1. 运行时动态构造 OA\JsonContent（Swagger 框架内部使用）
 *  2. 自定义 OpenApi 修改器（参见 plugin/nanoadmin/app/route/route.php 的 modify 回调）
 *
 * 控制器注解里仍然要 inline 写 new OA\JsonContent(allOf: [...])，
 * 但 schema 类（ApiResponse / PagedResponse / 业务 Response）可以引用常量 ::class。
 *
 * 提供这些方法是为了：
 *  - 集中管理 allOf 结构（一处改动全文档同步）
 *  - 业务方写 modify 回调时直接复用
 */
final class ApiResponseDocs
{
    private function __construct() {}

    /**
     * 分页响应的 JsonContent
     */
    public static function pagedJsonContent(): OA\JsonContent
    {
        return new OA\JsonContent(allOf: [
            new OA\Schema(ref: ApiResponse::class),
            new OA\Schema(properties: [
                new OA\Property(property: 'data', ref: PagedResponse::class),
            ]),
        ]);
    }

    /**
     * 单个资源的 JsonContent（data 为业务 schema）
     */
    public static function itemJsonContent(string $responseSchemaClass): OA\JsonContent
    {
        return new OA\JsonContent(allOf: [
            new OA\Schema(ref: ApiResponse::class),
            new OA\Schema(properties: [
                new OA\Property(property: 'data', ref: $responseSchemaClass),
            ]),
        ]);
    }

    /**
     * 空响应的 JsonContent（data 为 null）
     */
    public static function emptyJsonContent(): OA\JsonContent
    {
        return new OA\JsonContent(ref: ApiResponse::class);
    }

    /**
     * 通用 200 成功响应的 JsonContent（data 为 null）
     *
     * 给没写响应注解的接口兜底用，让前端在 OpenAPI 文档里能看到一个标准的成功示例。
     * 当前与 emptyJsonContent 等价，未来需要扩展示例时单点修改即可。
     */
    public static function successJsonContent(): OA\JsonContent
    {
        return self::emptyJsonContent();
    }

    /**
     * 通用 401/403 响应注解
     * @return array<int, OA\Response>
     */
    public static function commonErrorResponses(): array
    {
        return [
            new OA\Response(
                response: 401,
                description: '未授权',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(ref: ApiResponse::class)
                )
            ),
            new OA\Response(
                response: 403,
                description: '权限不足',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(ref: ApiResponse::class)
                )
            ),
        ];
    }
}
