<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\library\swagger\annotation\response;

use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;

/**
 * 分页响应
 *
 * 响应格式：
 * {
 *   "code": 20000,
 *   "msg": "操作成功",
 *   "data": {
 *     "total": 100,
 *     "current": 1,
 *     "size": 20,
 *     "records": [ ... ]
 *   }
 * }
 *
 * 示例：
 * #[PageResponse(schema: AdminResponse::class)]
 * #[PageResponse(example: [['id' => 1, 'name' => 'test']])]
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class PageResponse extends AbstractResponse
{
    /**
     * @param mixed $schema Schema 类名
     * @param mixed $example items 示例数据
     * @param string $description 响应描述
     * @param int $response HTTP 状态码
     * @param int|null $totalExample total 示例值
     * @param int|null $currentExample current 示例值
     * @param int|null $sizeExample size 示例值
     */
    public function __construct(
        mixed $schema = null,
        mixed $example = null,
        string $description = '成功',
        int $response = self::STATUS_SUCCESS,
        ?int $totalExample = null,
        ?int $currentExample = null,
        ?int $sizeExample = null,
        array $headers = []
    ) {
        $this->totalExample = $totalExample ?? 100;
        $this->currentExample = $currentExample ?? 1;
        $this->sizeExample = $sizeExample ?? 20;
        $this->recordsExample = $example;

        parent::__construct($schema, null, $description, $response, $headers);
    }

    private ?int $totalExample = null;
    private ?int $currentExample = null;
    private ?int $sizeExample = null;
    private mixed $recordsExample = null;

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
                    type: 'object'
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
    protected function buildDataProperties(): array
    {
        $properties = [
            new Property(property: 'total', type: 'integer', example: $this->totalExample),
            new Property(property: 'current', type: 'integer', example: $this->currentExample),
            new Property(property: 'size', type: 'integer', example: $this->sizeExample),
        ];

        // 如果有 schema 类，提取其属性定义
        if ($this->schema !== null && class_exists($this->schema)) {
            $itemProperties = $this->extractPropertiesFromSchema($this->schema);

            $properties[] = new Property(
                property: 'records',
                type: 'array',
                items: new Items(properties: $itemProperties, type: 'object')
            );
        }
        // 如果有 example 数据，从 example 推断属性
        elseif ($this->recordsExample !== null && is_array($this->recordsExample) && !empty($this->recordsExample)) {
            $firstItem = $this->recordsExample[0] ?? [];
            if (is_array($firstItem) && !empty($firstItem)) {
                $itemProperties = $this->extractPropertiesFromExample($firstItem);

                $properties[] = new Property(
                    property: 'records',
                    type: 'array',
                    items: new Items(properties: $itemProperties, type: 'object')
                );
            } else {
                $properties[] = new Property(property: 'records', example: $this->recordsExample);
            }
        } else {
            $properties[] = new Property(property: 'records', type: 'array', items: new Items());
        }

        return $properties;
    }

    /**
     * 构建示例数据
     */
    protected function buildExample(): ?array
    {
        $records = $this->recordsExample;

        // 如果有 schema 类但没有 example，从 schema 提取示例
        if ($records === null && $this->schema !== null && class_exists($this->schema)) {
            $itemProperties = $this->extractPropertiesFromSchema($this->schema);
            $example = [];
            foreach ($itemProperties as $prop) {
                $example[$prop->property] = $prop->example;
            }
            $records = [$example];
        }

        return [
            'code' => self::CODE_SUCCESS,
            'msg' => self::MESSAGE_SUCCESS,
            'data' => [
                'total' => $this->totalExample,
                'current' => $this->currentExample,
                'size' => $this->sizeExample,
                'records' => $records,
            ],
            'timestamp' => time(),
        ];
    }
}
