<?php

namespace plugin\nanoadmin\app\controller;

use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\validator\ValidatorBase;
use support\Request;

/**
 * 资源控制器抽象基类
 *
 * 校验策略：使用 ValidatorBase 进行参数校验
 *
 * 子类使用模式：
 *  - 声明 $queryValidator / $createValidator / $updateValidator
 *  - 调用 validateQuery() / validateCreate() / validateUpdate() 获取校验后的数据
 *
 * OpenAPI 文档：
 *  - 在控制器方法上使用 OA 注解 + x: [X_SCHEMA_TO_PARAMETERS] / x: [X_REQUEST_BODY]
 *  - 由 OpenApiModifier 自动处理文档生成
 *
 * 错误处理：校验失败统一抛 ApiException(VALIDATION_ERROR)，由异常中间件转 R::error
 */
abstract class AbstractResourceController extends BaseController
{
    /**
     * 查询参数 ValidatorBase
     */
    protected ?string $queryValidator = null;

    /**
     * 创建参数 ValidatorBase
     */
    protected ?string $createValidator = null;

    /**
     * 更新参数 ValidatorBase
     */
    protected ?string $updateValidator = null;

    /**
     * 业务标签名（用于 OpenAPI tag）
     * 必填
     */
    protected string $resourceTag;

    /**
     * 校验 query 参数
     *
     * @return array 校验后的干净数据
     */
    protected function validateQuery(Request $request): array
    {
        return $this->doValidate($this->queryValidator, $request->get());
    }

    /**
     * 校验 create 参数
     *
     * @return array 校验后的干净数据
     */
    protected function validateCreate(Request $request): array
    {
        return $this->doValidate($this->createValidator, $request->post());
    }

    /**
     * 校验 update 参数
     *
     * @return array 校验后的干净数据
     */
    protected function validateUpdate(Request $request): array
    {
        return $this->doValidate($this->updateValidator, $request->post());
    }

    /**
     * 执行 ValidatorBase 校验
     *
     * @return array 校验后的干净数据
     */
    private function doValidate(?string $validatorClass, array $data): array
    {
        if (!$validatorClass) {
            return $data;
        }

        $this->ensureValidatorClass($validatorClass);

        /** @var ValidatorBase $validator */
        $validator = new $validatorClass();
        // validated() 内部会调用 validateData()，校验失败已抛 ApiException
        return $validator->validated();
    }

    private function ensureValidatorClass(string $class): void
    {
        if (!class_exists($class)) {
            throw new ApiException(
                "Validator class not found: {$class}",
                Code::VALIDATION_ERROR->value
            );
        }
        if (!is_subclass_of($class, ValidatorBase::class)) {
            throw new ApiException(
                "Validator class {$class} must extend " . ValidatorBase::class,
                Code::VALIDATION_ERROR->value
            );
        }
    }
}
