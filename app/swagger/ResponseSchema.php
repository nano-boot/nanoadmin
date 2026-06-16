<?php

namespace plugin\nanoadmin\app\swagger;

/**
 * 响应结构 schema 基类
 *
 * 用于详情/列表项的 data 字段：
 *  - 控制器中通过 AbstractResourceController::$responseSchema 声明
 *  - OA 注解里 allOf: [ApiResponse, data: ref: XxxResponse]
 */
abstract class ResponseSchema extends ResourceSchema
{
    public string $schemaKind = 'response';
}
