<?php

namespace plugin\theadmin\app\common;

use Exception;

/**
 * API异常类
 */
class ApiException extends Exception
{
    /**
     * 错误码
     */
    protected int|Code $errorCode;

    /**
     * HTTP状态码
     */
    protected $httpCode;

    /**
     * 额外数据
     */
    protected $data;

    public function __construct(Code|int $errorCode, string $message = '', int $httpCode = 0, $data = null)
    {
        // 处理枚举和整数两种类型
        if ($errorCode instanceof Code) {
            $this->errorCode = $errorCode->value;
            $this->httpCode = $httpCode ?: $errorCode->getHttpCode();

            // 如果没有提供消息，使用枚举的默认消息
            if (empty($message)) {
                $message = $errorCode->getMessage();
            }
        } else {
            $this->errorCode = $errorCode;
            $this->httpCode = $httpCode ?: Code::getHttpCodeByCode($errorCode);

            // 如果没有提供消息，使用默认消息
            if (empty($message)) {
                $message = Code::getMessageByCode($errorCode);
            }
        }

        $this->data = $data;
        parent::__construct($message);
    }

    /**
     * 获取错误码
     */
    public function getErrorCode(): int
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
    public function getData()
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
            'message' => $this->getMessage(),
            'data' => $this->data,
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