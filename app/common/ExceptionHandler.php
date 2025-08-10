<?php

namespace plugin\theadmin\app\common;

use app\common\ApiException;
use app\common\ApiResponse;
use app\common\ErrorCode;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use support\Log;

/**
 * 全局异常处理器
 */
class ExceptionHandler
{
    /**
     * 处理异常
     */
    public function handle(Request $request, Throwable $exception): Response
    {
        // 处理API异常
        if ($exception instanceof ApiException) {
            return $this->handleApiException($exception);
        }

        // 处理验证异常
        if ($exception instanceof \InvalidArgumentException) {
            return $this->handleValidationException($exception);
        }

        // 处理数据库异常
        if ($exception instanceof \PDOException) {
            return $this->handleDatabaseException($exception);
        }

        // 处理404异常
        if ($exception instanceof \Webman\Exception\NotFoundException) {
            return $this->handleNotFoundException($exception);
        }

        // 处理方法不允许异常
        if ($exception instanceof \Webman\Exception\MethodNotAllowedException) {
            return $this->handleMethodNotAllowedException($exception);
        }

        // 记录系统异常日志
        $this->logException($exception);

        // 返回系统错误响应
        return $this->systemErrorResponse($exception);
    }

    /**
     * 处理API异常
     */
    private function handleApiException(ApiException $exception): Response
    {
        $data = $exception->toArray();
        $httpCode = $exception->getHttpCode();

        // 记录错误日志（非系统错误）
        if ($exception->getErrorCode() >= ErrorCode::SYSTEM_ERROR) {
            Log::error('API异常', [
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }

        return json($data, $httpCode);
    }

    /**
     * 处理验证异常
     */
    private function handleValidationException(\InvalidArgumentException $exception): Response
    {
        $data = ApiResponse::parameterError($exception->getMessage());
        return json($data, 400);
    }

    /**
     * 处理数据库异常
     */
    private function handleDatabaseException(\PDOException $exception): Response
    {
        // 记录数据库异常日志
        Log::error('数据库异常', [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        // 根据环境返回不同的错误信息
        if (config('app.debug')) {
            $message = '数据库错误: ' . $exception->getMessage();
        } else {
            $message = '数据库操作失败';
        }

        $data = ApiResponse::error(ErrorCode::DATABASE_ERROR, $message);
        return json($data, 500);
    }

    /**
     * 处理404异常
     */
    private function handleNotFoundException(\Webman\Exception\NotFoundException $exception): Response
    {
        $data = ApiResponse::notFound('接口不存在');
        return json($data, 404);
    }

    /**
     * 处理方法不允许异常
     */
    private function handleMethodNotAllowedException(\Webman\Exception\MethodNotAllowedException $exception): Response
    {
        $data = ApiResponse::error(ErrorCode::METHOD_NOT_ALLOWED, '请求方法不允许');
        return json($data, 405);
    }

    /**
     * 记录系统异常日志
     */
    private function logException(Throwable $exception): void
    {
        Log::error('系统异常', [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * 系统错误响应
     */
    private function systemErrorResponse(Throwable $exception): Response
    {
        // 根据环境返回不同的错误信息
        if (config('app.debug')) {
            $message = $exception->getMessage();
            $data = ApiResponse::systemError($message);
        } else {
            $data = ApiResponse::systemError('系统内部错误');
        }

        return json($data, 500);
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp(Request $request): string
    {
        return $request->getRealIp() ?? 'unknown';
    }

    /**
     * 获取用户代理
     */
    private function getUserAgent(Request $request): string
    {
        return $request->header('User-Agent') ?? 'unknown';
    }
}