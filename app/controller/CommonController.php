<?php

namespace plugin\nanoadmin\app\controller;

use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\validator\ValidatorBase;
use support\Request;

/**
 * 通用 CRUD 控制器基类
 *
 * 定位：nanoadmin 中带"标准约定"的标准 CRUD 控制器骨架
 *  - 比 BaseController 多一层通用约定：参数校验 + OpenAPI 文档 + Middleware 声明
 *  - 适合绝大多数后台业务模块（管理员/角色/菜单/字典…）
 *
 * 子类使用模式：
 *  - 声明 $queryValidator / $createValidator / $updateValidator
 *  - 调用 validateQuery() / validateCreate() / validateUpdate() 获取校验后的数据
 *  - 在方法上使用 OA 注解 + x: [X_SCHEMA_TO_PARAMETERS] / x: [X_REQUEST_BODY]
 *  - 路由由 OpenApiRouteRegister 通过 OA 注解自动注册
 *
 * 错误处理：校验失败统一抛 ApiException(VALIDATION_ERROR)，由异常中间件转 R::error
 *
 *
 * 与 BaseController 的关系：
 *  - BaseController：极简 CRUD 骨架，不做参数校验、无注解约定
 *  - CommonController：标准 CRUD + ValidatorBase 校验 + OpenAPI 注解 + Middleware 注解
 */
abstract class CommonController extends BaseController
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

        $validator = new $validatorClass();
        // validated() 内部会调用 validateData()，校验失败已抛 ApiException
        return $validator->validated();
    }

    /**
     * 验证验证器类是否合法
     *
     * @param string $class
     * @throws ApiException
     */
    private function ensureValidatorClass(string $class): void
    {
        if (!class_exists($class)) {
            throw new ApiException(
                "Validator class not found: {$class}",
                Code::VALIDATION_ERROR->value
            );
        }

        $validBases = ValidatorBase::class;
        $isValid = false;
        if (is_subclass_of($class, $validBases)) {
            $isValid = true;
        }

        if (!$isValid) {
            throw new ApiException(
                "Validator class {$class} must extend {$validBases}",
                Code::VALIDATION_ERROR->value
            );
        }
    }
}
