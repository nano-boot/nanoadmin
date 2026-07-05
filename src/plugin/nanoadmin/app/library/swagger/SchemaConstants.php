<?php

namespace plugin\nanoadmin\app\library\swagger;

/**
 * OpenAPI 注解扩展字段（x-*）的常量定义
 *
 * 之前从 webman-tech/swagger 包里拿过来的常量值，这里统一在自己命名空间下维护。
 * 业务方在 OA\Get / OA\Post 等注解里通过 x: [SchemaConstants::FOO => ...] 引用。
 */
final class SchemaConstants
{
    private function __construct() {}

    /**
     * x[schema-to-parameters]: 把 query schema 类转成 query parameters
     *
     * 用法：
     *   #[OA\Get(
     *       path: '/sys/admin',
     *       x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => AdminQuery::class]
     *   )]
     *
     * 配合 Processors/SchemaQueryParameter 在 swagger-php validate() 之前生效。
     */
    public const X_SCHEMA_TO_PARAMETERS = 'schema-to-parameters';

    /**
     * x[schema-to-request-body]: 把 request schema 类转成 requestBody
     *
     * 用法：
     *   #[OA\Post(
     *       path: '/sys/admin',
     *       x: [SchemaConstants::X_SCHEMA_TO_REQUEST_BODY => AdminCreateRequest::class]
     *   )]
     */
    public const X_SCHEMA_TO_REQUEST_BODY = 'schema-to-request-body';

    /**
     * x[route-name]: 命名路由
     */
    public const X_NAME = 'route-name';

    /**
     * x[route-path]: 覆盖 operation.path（用于 /user/{id:\d+} 等正则路径）
     */
    public const X_PATH = 'route-path';

    /**
     * x[route-middleware]: 路由级中间件
     */
    public const X_MIDDLEWARE = 'route-middleware';
}
