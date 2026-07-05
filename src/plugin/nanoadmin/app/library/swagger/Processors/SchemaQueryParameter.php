<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\library\swagger\Processors;

use OpenApi\Analysis;
use OpenApi\Annotations\Operation;
use OpenApi\Attributes\Parameter;
use OpenApi\Annotations\Schema as AnSchema;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use OpenApi\Processors\ProcessorInterface;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;

/**
 * 把 operation 上的 x[schema-to-parameters] 指向的 schema 类展开成 query parameters。
 *
 * 控制器写法：
 *   #[OA\Get(
 *       path: '/sys/admin',
 *       x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => AdminQuery::class]
 *   )]
 *
 * 处理时机：swagger-php validate() 之前，让所有 query 参数正确出现在文档里。
 *
 * 来源：早期从 webman-tech/swagger 包里拿过来，逻辑等价，本地维护，去掉对 webman-tech 的依赖。
 * @see https://github.com/zircote/swagger-php/blob/master/Examples/processors/schema-query-parameter/SchemaQueryParameter.php
 */
class SchemaQueryParameter implements ProcessorInterface
{
    private const REF = SchemaConstants::X_SCHEMA_TO_PARAMETERS;

    private Analysis $analysis;

    public function __invoke(Analysis $analysis): void
    {
        $this->analysis = $analysis;
        /** @var Operation[] $operations */
        $operations = $analysis->getAnnotationsOfType(Operation::class);

        foreach ($operations as $operation) {
            if (Generator::isDefault($operation->x) || !array_key_exists(self::REF, $operation->x)) {
                continue;
            }

            if (!is_string($operation->x[self::REF])) {
                throw new \InvalidArgumentException('Value of `x.' . self::REF . '` must be a string');
            }

            $schema = $analysis->getSchemaForSource($operation->x[self::REF]);
            if (!$schema instanceof AnSchema) {
                throw new \InvalidArgumentException(
                    'Value of `x.' . self::REF . '` contains reference to unknown schema: `' . $operation->x[self::REF] . '`'
                );
            }

            $this->expandQueryArgs($operation, $schema);
            $this->cleanUp($operation);
        }
    }

    private function expandQueryArgs(Operation $operation, AnSchema $schema): void
    {
        if (!Generator::isDefault($schema->allOf)) {
            foreach ($schema->allOf as $itemSchema) {
                $this->expandQueryArgs($operation, $itemSchema);
            }
        }

        if (!Generator::isDefault($schema->ref)) {
            $refSchema = $this->analysis->openapi->ref($schema->ref);
            if (!$refSchema instanceof AnSchema) {
                throw new \InvalidArgumentException('ref must be a schema reference');
            }
            $this->expandQueryArgs($operation, $refSchema);
        }

        if (Generator::isDefault($schema->properties) || !$schema->properties) {
            return;
        }

        $operation->parameters = Generator::isDefault($operation->parameters) ? [] : $operation->parameters;

        foreach ($schema->properties as $property) {
            $isNullable = Generator::isDefault($property->nullable) ? false : $property->nullable;
            $schemaNew = new Schema(
                type: Generator::isDefault($property->format) ? $property->type : $property->format,
                nullable: $isNullable
            );
            $schemaNew->_context = $operation->_context;

            $parameter = new Parameter(
                name: $property->property,
                description: Generator::isDefault($property->description) ? null : $property->description,
                in: 'query',
                required: !$isNullable,
                schema: $schemaNew,
                example: $property->example,
            );
            $parameter->_context = $operation->_context;

            $operation->parameters[] = $parameter;
        }
    }

    private function cleanUp(Operation $operation): void
    {
        unset($operation->x[self::REF]);
        if (!$operation->x) {
            $operation->x = Generator::UNDEFINED;
        }
    }
}
