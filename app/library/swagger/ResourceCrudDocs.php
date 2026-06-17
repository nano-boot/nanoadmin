<?php

namespace plugin\nanoadmin\app\library\swagger;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;

/**
 * CRUD 注解片段工厂
 *
 * 提供 CRUD 接口常用注解片段的工厂方法。控制器里写：
 *   #[OA\Get(path: '/sys/admin', ...ResourceCrudDocs::pageList(self::class))]
 *
 * 静态方法返回数组，可直接 inline 到 OA\Get 的属性中。
 */
final class ResourceCrudDocs
{
    private function __construct() {}

    /**
     * 列表接口的 x 扩展数组（绑定 query schema 自动生成参数）
     * 用法：x: ResourceCrudDocs::pageList(MyQuery::class)
     */
    public static function pageList(string $querySchemaClass): array
    {
        return [SchemaConstants::X_SCHEMA_TO_PARAMETERS => $querySchemaClass];
    }

    /**
     * 路径参数 {id} 的 OA\Parameter
     */
    public static function idPathParameter(string $name = 'id', string $description = 'ID'): OA\Parameter
    {
        return new OA\Parameter(
            name: $name,
            description: $description,
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        );
    }
}
