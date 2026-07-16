<?php

namespace plugin\nanoadmin\app\common;

use Exception;

/**
 * API异常类
 *
 * 构造签名：(Code|int $code = 40000, string $message = '', int $httpCode = 0, mixed $data = null)
 *
 * 用法示例：
 *   throw new ApiException();                          // code=40000, message=默认
 *   throw new ApiException(Code::UNAUTHORIZED);        // code=40100, message=枚举默认值
 *   throw new ApiException(Code::UNAUTHORIZED, '未登录'); // code=40100, message=自定义
 *   throw new ApiException('纯消息');                  // code=40000, message=自定义
 *   throw new ApiException(40001, '自定义参数错误');    // code=40001, message=自定义
 */
class ApiException extends Exception
{
    /**
     * 业务错误码（独立于 Exception::$code）
     */
    protected int|Code $errorCode;

    /**
     * HTTP状态码
     */
    protected int $httpCode;

    /**
     * 额外数据
     */
    protected mixed $data;

    public function __construct(Code|int $code = 40000, string $message = '', ?int $httpCode = null, mixed $data = null)
    {
        // 处理枚举和整数两种类型
        if ($code instanceof Code) {
            $this->errorCode = $code->value;
            $this->httpCode = $httpCode ?? $code->getHttpCode();

            // 如果没有提供消息，使用枚举的默认消息
            if (empty($message)) {
                $message = $code->getMessage();
            }
        } else {
            $this->errorCode = $code;
            $this->httpCode = $httpCode ?? Code::getHttpCodeByCode($code);

            // 如果没有提供消息，使用默认消息
            if (empty($message)) {
                $message = Code::getMessageByCode($code);
            }
        }

        $this->data = $data;
        parent::__construct($message);
    }

    /**
     * 获取业务错误码
     * @deprecated use getApiCode() instead
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * 获取业务错误码（与 parent::getCode() 区分）
     */
    public function getApiCode(): int
    {
        return $this->errorCode;
    }

    /**
     * 获取HTTP状态码
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * 获取额外数据
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'code' => $this->errorCode,
            'msg' => $this->getMessage(),
            'data' => $this->data,
            'file' => $this->getFile(),
            'traces' => '[' . $this->getLine() . '] ' . $this->getTraceAsString(),
            'timestamp' => time(),
        ];
    }

    /**
     * 创建参数错误异常
     */
    public static function parameterError(string $message = ''): self
    {
        return new self(Code::PARAMETER_ERROR, $message);
    }

    /**
     * 创建未授权异常
     */
    public static function unauthorized(string $message = ''): self
    {
        return new self(Code::UNAUTHORIZED, $message);
    }

    /**
     * 创建权限不足异常
     */
    public static function forbidden(string $message = ''): self
    {
        return new self(Code::FORBIDDEN, $message);
    }

    /**
     * 创建资源不存在异常
     */
    public static function notFound(string $message = ''): self
    {
        return new self(Code::NOT_FOUND, $message);
    }

    /**
     * 创建系统错误异常
     */
    public static function systemError(string $message = ''): self
    {
        return new self(Code::SYSTEM_ERROR, $message);
    }
}
