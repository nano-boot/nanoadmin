<?php

namespace plugin\nanoadmin\app\swagger;

/**
 * 资源级 Schema 基类（统一继承点）
 *
 * 所有 4 种 schema 都继承自此类：
 *  - QuerySchema：分页/查询参数
 *  - RequestSchema：创建/更新请求体
 *  - ResponseSchema：响应结构
 *  - ResourceSchema：通用基类（默认 kind=response）
 *
 * 业务用法：
 *   - 字段都用 #[OA\Property(...)] 标注，仅作 OpenAPI 文档生成用
 *   - 校验统一交给 plugin\nanoadmin\app\validator\ValidatorBase 子类
 *   - 控制器通过 $queryValidator / $createValidator / $updateValidator 声明使用
 *
 * 历史：
 *   早期该类继承 webman-tech/swagger 的 BaseSchema（提供 load/toArray 能力），
 *   后续发现业务里没用到这些方法，为去掉对 webman-tech 的依赖，简化为空抽象类。
 *   如未来需要 fromArray / toArray 工具，可在此处补回。
 */
abstract class ResourceSchema
{
    /**
     * schema 用途：'query' / 'request' / 'response' / 'list'
     * 子类可在构造里覆盖为对应值
     */
    public string $schemaKind = 'response';
}
