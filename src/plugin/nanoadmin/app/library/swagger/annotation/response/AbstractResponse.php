<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\library\swagger\annotation\response;

use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response as BaseResponse;
use OpenApi\Attributes\Property;
use plugin\nanoadmin\app\library\swagger\ApiResponseDocs;

/**
 * 响应注解基类
 *
 * 继承 OpenApi\Attributes\Response，提供统一的响应模板
 * 支持多种数据格式：
 * - schema: 类路径
 * - example: 数组
 */
abstract class AbstractResponse extends BaseResponse
{
    /** 成功状态码 */
    public const STATUS_SUCCESS = 200;

    /** 业务成功码 */
    public const CODE_SUCCESS = 20000;

    /** 业务消息 */
    public const MESSAGE_SUCCESS = '操作成功';

    /**
     * @param mixed $schema Schema 类名
     * @param mixed $example 示例数据
     * @param string $description 响应描述
     * @param int $response HTTP 状态码
     * @param array $headers 响应头
     */
    public function __construct(
        mixed $schema = null,
        mixed $example = null,
        string $description = '成功',
        int $response = self::STATUS_SUCCESS,
        array $headers = []
    ) {
        $this->schema = $schema;
        $this->example = $example;

        parent::__construct(
            response: $response,
            description: $description,
            headers: $headers,
            content: $this->buildContent()
        );
    }

    /**
     * 构建响应内容
     */
    abstract protected function buildContent(): JsonContent;

    /**
     * 获取业务代码属性定义
     */
    protected function getCodeProperty(): Property
    {
        return new Property(
            property: 'code',
            type: 'integer',
            example: self::CODE_SUCCESS,
            description: '状态码，20000表示成功'
        );
    }

    /**
     * 获取消息属性定义
     */
    protected function getMsgProperty(): Property
    {
        return new Property(
            property: 'msg',
            type: 'string',
            example: self::MESSAGE_SUCCESS
        );
    }

    /**
     * 获取时间戳属性定义
     */
    protected function getTimestampProperty(): Property
    {
        return new Property(
            property: 'timestamp',
            type: 'integer',
            example: time()
        );
    }

    /**
     * 从 Schema 类提取属性定义
     */
    protected function extractPropertiesFromSchema(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        $reflection = new \ReflectionClass($className);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $propAttributes = $property->getAttributes(Property::class);

            if (!empty($propAttributes)) {
                $prop = $propAttributes[0]->newInstance();
                // PHP 8 attribute 装饰在类属性上时，无法在注解里写 property 名称（必须为常量表达式），
                // 因此 newInstance() 出来的 Property->property 是 Generator::UNDEFINED。
                // 这里手动回填为 PHP 属性名，避免 swagger-php 校验时把多条 Property 误判为同 key="" 的重复。
                if (\OpenApi\Generator::isDefault($prop->property)) {
                    $prop->property = $property->getName();
                }
                $properties[] = $prop;
            } else {
                $type = $property->getType();
                $typeName = $type ? $type->getName() : 'string';

                $properties[] = new Property(
                    property: $property->getName(),
                    type: $this->mapPhpTypeToOpenApi($typeName),
                    example: $this->getExampleValueByType($typeName)
                );
            }
        }

        return $properties;
    }

    /**
     * 将 PHP 类型映射到 OpenAPI 类型
     */
    protected function mapPhpTypeToOpenApi(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'string' => 'string',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            default => 'string',
        };
    }

    /**
     * 根据类型获取示例值
     */
    protected function getExampleValueByType(string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => 0,
            'float', 'double' => 0.0,
            'string' => 'string',
            'bool', 'boolean' => true,
            'array' => [],
            default => null,
        };
    }

    /**
     * 从示例数据提取属性定义
     *
     * 仅当 $data 为关联数组（object 形态）时返回字段列表。
     * 若 $data 是列表数组（array 形态），返回空数组 —— 调用方应使用 buildArrayProperty() 描述 array+items。
     */
    protected function extractPropertiesFromExample(array $data): array
    {
        if (!$this->isAssociativeArray($data)) {
            return [];
        }

        $properties = [];

        foreach ($data as $key => $value) {
            $type = $this->inferTypeFromValue($value);

            $properties[] = new Property(
                property: (string) $key,
                type: $type,
                example: $value
            );
        }

        return $properties;
    }

    /**
     * 从列表型示例数据推断 items 定义
     */
    protected function buildItemsFromListExample(array $list): ?\OpenApi\Attributes\Items
    {
        if (empty($list)) {
            return null;
        }

        $first = $list[array_key_first($list)];

        if (is_array($first) && $this->isAssociativeArray($first)) {
            return new \OpenApi\Attributes\Items(
                properties: $this->extractPropertiesFromExample($first),
                type: 'object'
            );
        }

        return new \OpenApi\Attributes\Items(type: $this->inferTypeFromValue($first));
    }

    /**
     * 判断是否为关联数组（object 形态），而非列表数组（array 形态）
     */
    protected function isAssociativeArray(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        return array_keys($data) !== range(0, count($data) - 1);
    }

    /**
     * 从值推断类型
     */
    protected function inferTypeFromValue(mixed $value): string
    {
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'number';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_array($value)) {
            if (empty($value) || array_keys($value) !== range(0, count($value) - 1)) {
                return 'object';
            }
            return 'array';
        }
        return 'string';
    }

    /** Schema 类名 */
    protected mixed $schema = null;

    /** 示例数据 */
    protected mixed $example = null;
}
