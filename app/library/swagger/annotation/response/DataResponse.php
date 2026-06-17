<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\library\swagger\annotation\response;

use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;

/**
 * 数据响应
 *
 * 响应格式：
 * {
 *   "code": 20000,
 *   "msg": "操作成功",
 *   "data": null
 * }
 *
 * 示例：
 * #[DataResponse()]
 * #[DataResponse(example: ['id' => 1])]
 * #[DataResponse(schema: AdminResponse::class)]
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class DataResponse extends AbstractResponse
{
    /**
     * @param mixed $schema Schema 类名
     * @param mixed $example 示例数据
     * @param string $description 响应描述
     * @param int $response HTTP 状态码
     */
    public function __construct(
        mixed $schema = null,
        mixed $example = null,
        string $description = '成功',
        int $response = self::STATUS_SUCCESS,
        array $headers = []
    ) {
        parent::__construct($schema, $example, $description, $response, $headers);
    }

    protected function buildContent(): JsonContent
    {
        $dataProperties = $this->buildDataProperties();

        return new JsonContent(
            properties: [
                $this->getCodeProperty(),
                $this->getMsgProperty(),
                new Property(
                    property: 'data',
                    properties: $dataProperties,
                    type: $dataProperties ? 'object' : null
                ),
                $this->getTimestampProperty(),
            ],
            type: 'object',
            example: $this->buildExample()
        );
    }

    /**
     * 构建 data 属性的子属性
     */
    protected function buildDataProperties(): ?array
    {
        // 如果有 schema 类，提取其属性定义
        if ($this->schema !== null && class_exists($this->schema)) {
            return $this->extractPropertiesFromSchema($this->schema);
        }

        // 如果有 example 数据，从 example 推断属性
        if ($this->example !== null && is_array($this->example) && !empty($this->example)) {
            return $this->extractPropertiesFromExample($this->example);
        }

        return null;
    }

    /**
     * 构建示例数据
     */
    protected function buildExample(): ?array
    {
        $data = null;

        // 如果有 schema 类但没有 example，从 schema 提取示例
        if ($this->schema !== null && class_exists($this->schema)) {
            $properties = $this->extractPropertiesFromSchema($this->schema);
            $data = [];
            foreach ($properties as $prop) {
                $data[$prop->property] = $prop->example;
            }
        } elseif ($this->example !== null && is_array($this->example)) {
            $data = $this->example;
        }

        return [
            'code' => self::CODE_SUCCESS,
            'msg' => self::MESSAGE_SUCCESS,
            'data' => $data,
            'timestamp' => time(),
        ];
    }
}
