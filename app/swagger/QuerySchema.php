<?php

namespace plugin\nanoadmin\app\swagger;

/**
 * 查询参数 schema 基类
 *
 * 用于列表/搜索接口的 query 参数：
 *  - 自动生成 OpenAPI 文档中的 parameters
 *  - 自动调用 laravel-validation 校验
 *  - 控制器中通过 AbstractResourceController::validateQuery() 使用
 */
abstract class QuerySchema extends ResourceSchema
{
    public string $schemaKind = 'query';
}
