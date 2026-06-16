<?php

namespace plugin\nanoadmin\app\swagger;

use plugin\nanoadmin\app\validator\ValidatorBase;
use WebmanTech\Swagger\SchemaAnnotation\BaseSchema;

/**
 * 资源级 Schema 基类（统一继承点）
 *
 * 所有 4 种 schema 都继承自此类：
 *  - QuerySchema：分页/查询参数
 *  - RequestSchema：创建/更新请求体
 *  - ResponseSchema：响应结构
 *  - ResourceSchema：通用基类（默认 kind=response）
 *
 * 【校验职责】
 * 注解 schema 本身不再承担校验——校验统一交给 ValidatorBase。
 * 控制器声明 $queryValidator = XxxValidator::class 即可启用 ValidatorBase 校验。
 * 缺省时仍可走 schema 自带的 validationExtraRules（不推荐）。
 *
 * 用法：
 *   $data = LogLoginQuery::fromValidator($validator);  // 校验 + 回填
 *   $instance = LogLoginQuery::fromArray($data);        // 不校验，回填
 */
abstract class ResourceSchema extends BaseSchema
{
    /**
     * schema 用途：'query' / 'request' / 'response' / 'list'
     * 子类可在构造里覆盖为对应值
     */
    public string $schemaKind = 'response';

    /**
     * 从 ValidatorBase 校验后构造 schema 实例
     *
     * 业务方最简调用：
     *   $validator = new LogLoginValidator();
     *   $instance = LogLoginQuery::fromValidator($validator);
     *
     * @param ValidatorBase $validator 已经实例化的 ValidatorBase
     * @param string|null $scene 可选，校验场景；缺省由 ValidatorBase::getSceneName() 自动推断
     * @return static
     * @throws \plugin\nanoadmin\app\common\ApiException 校验失败时
     */
    public static function fromValidator(ValidatorBase $validator, ?string $scene = null): static
    {
        $data = $scene ? $validator->validated($scene) : $validator->validated();
        return static::fromArray($data);
    }

    /**
     * 从原始数据构造 schema 实例（不触发校验）
     *
     * 适用于：
     *  - 已经走过 ValidatorBase 校验后回填
     *  - 测试场景
     *  - Service 内部加载数据库结果
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();
        return $instance->load($data);
    }
}

