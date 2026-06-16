<?php

namespace plugin\nanoadmin\app\swagger;

/**
 * 请求体 schema 基类
 *
 * 用于创建/更新接口的 body 参数：
 *  - 自动生成 OpenAPI 文档中的 requestBody
 *  - 自动调用 laravel-validation 校验
 *  - 控制器中通过 AbstractResourceController::validateCreate()/validateUpdate() 使用
 *
 * 可通过 schemaKind 区分 create / update：
 *   class AdminCreateRequest extends RequestSchema { public string $schemaKind = 'request.create'; }
 *   class AdminUpdateRequest extends RequestSchema { public string $schemaKind = 'request.update'; }
 */
abstract class RequestSchema extends ResourceSchema
{
    public string $schemaKind = 'request';
}
