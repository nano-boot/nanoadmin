<?php

namespace plugin\theadmin\app\common;

use support\exception\Handler;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use support\Log;

/**
 * 全局异常处理器
 */
class TheHandler extends Handler
{
    /**
     * 异常类型到处理方法的映射
     */
    private const EXCEPTION_HANDLERS = [
        ApiException::class => ['method' => 'handleApiException', 'status' => 200],
        \InvalidArgumentException::class => ['method' => 'handleValidationException', 'status' => 400],
        \PDOException::class => ['method' => 'handleDatabaseException', 'status' => 400],
        \Webman\Exception\NotFoundException::class => ['method' => 'handleNotFoundException', 'status' => 404],
    ];

    /**
     * 处理异常
     */
    public function render(Request $request, Throwable $exception): Response
    {
        // 查找对应的异常处理器
        foreach (self::EXCEPTION_HANDLERS as $exceptionClass => $handler) {
            if ($exception instanceof $exceptionClass) {
                $method = $handler['method'];
                return $this->$method($exception, $handler['status']);
            }
        }

        // 记录系统异常日志
        $this->logException($exception);

        // 返回系统错误响应
        return $this->systemErrorResponse($exception);
    }

    /**
     * 处理API异常
     */
    private function handleApiException(ApiException $exception, int $status = 200): Response
    {
        // 记录错误日志（非系统错误）
        if ($exception->getErrorCode() >= Code::SYSTEM_ERROR) {
            $this->logException($exception, 'API异常');
        }

        return $this->buildResponse($exception, $exception->getMessage(), $status);
    }

    /**
     * 处理验证异常
     */
    private function handleValidationException(\InvalidArgumentException $exception, int $status = 400): Response
    {
        return $this->buildResponse($exception, $exception->getMessage(), $status);
    }

    /**
     * 处理数据库异常
     */
    private function handleDatabaseException(\PDOException $exception, int $status = 400): Response
    {
        // 记录数据库异常日志
        $this->logException($exception, '数据库异常');
        
        return $this->buildResponse($exception, '数据库操作失败', $status);
    }

    /**
     * 处理404异常
     */
    private function handleNotFoundException(\Webman\Exception\NotFoundException $exception, int $status = 404): Response
    {
        return $this->buildResponse($exception, $exception->getMessage(), $status);
    }

    /**
     * 记录系统异常日志
     */
    private function logException(Throwable $exception, string $type = '系统异常'): void
    {
        Log::error($type, [
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
        $json = $this->buildJsonResponse($exception, '系统内部错误');
        
        return new Response(500, ['Content-Type' => 'application/json'],
            json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * 构建JSON响应
     */
    private function buildJsonResponse(Throwable $exception, string $defaultMessage): array
    {
        $json = [
            'code' => $exception->getCode(),
            'msg' => $defaultMessage,
            'data' => [],
        ];
        
        if ($this->debug) {
            $this->addDebugInfo($json, $exception);
        }
        
        return $json;
    }

    /**
     * 构建响应
     */
    private function buildResponse(Throwable $exception, string $message, int $status): Response
    {
        $json = $this->buildJsonResponse($exception, $message);
        return json($json, $status);
    }

    /**
     * 添加调试信息到响应数组
     */
    private function addDebugInfo(array &$json, Throwable $exception): void
    {
        $json['msg'] = $exception->getMessage();
        $json['file'] = $exception->getFile();
        $json['line'] = $exception->getLine();
        $json['traces'] = $exception->getTraceAsString();
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