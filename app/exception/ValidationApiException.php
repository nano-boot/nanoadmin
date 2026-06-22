<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\exception;

use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use support\validation\ValidationException as WebmanValidationException;

/**
 * 校验异常（webman/validation 适配）
 *
 * webman/validation 的 Validator::validate() 在失败时按 (message, httpCode) 调用异常构造函数，
 * 与项目 ApiException(Code|int $errorCode, string $message, int $httpCode, $data) 签名不一致。
 *
 * 本类作为中间异常，被 webman/validation 通过 withException() 链式注入；
 * 构造时立刻包装成 ApiException 并抛出，从而让全局异常处理器拿到统一的 ApiException。
 */
class ValidationApiException extends WebmanValidationException
{
    public function __construct(string $message = '', int $httpCode = 400)
    {
        parent::__construct($message, $httpCode);
        throw new ApiException(Code::VALIDATION_ERROR, $message, $httpCode ?: 400);
    }
}
