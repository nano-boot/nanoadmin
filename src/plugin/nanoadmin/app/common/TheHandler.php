<?php

namespace plugin\nanoadmin\app\common;

use support\exception\Handler;
use think\exception\ValidateException;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use support\Log;
use support\validation\ValidationException;
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
        ValidationException::class => ['method' => 'handleValidationException', 'status' => 400],
        \PDOException::class => ['method' => 'handleDatabaseException', 'status' => 400],
        \Webman\Exception\NotFoundException::class => ['method' => 'handleNotFoundException', 'status' => 404],];

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
    private function handleValidationException(ValidationException $exception, int $status = 400): Response
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
            'trace_string' => $exception->getTraceAsString(),
            'trace' => $this->formatStackTrace($exception->getTraceAsString()),
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
        $json = $exception instanceof ApiException ? [
            'code' => $exception->getErrorCode(),
            'msg' => $exception->getMessage(),
            'data' => $exception->getData(),
        ] : [
            'code' => Code::SYSTEM_ERROR->value,
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
        $json['trace_string'] = $exception->getTraceAsString();
        $json['trace'] = $this->formatStackTrace($exception->getTraceAsString());
    }

    /**
     * 格式化堆栈跟踪信息
     */
    private function formatStackTrace(string $traceString): array
    {
        $lines = explode("\n", trim($traceString));
        $formattedTraces = [];
        
        foreach ($lines as $index => $line) {
            if (empty($line)) continue;
            
            // 解析堆栈跟踪行
            if (preg_match('/^#(\d+)\s+(.+?)(?:\((\d+)\))?:\s*(.+)$/', $line, $matches)) {
                $filePath = $matches[2];
                $lineNumber = isset($matches[3]) ? (int)$matches[3] : null;
                $function = $matches[4];
                
                // 提取文件名和相对路径
                $fileName = basename($filePath);
                $relativePath = $this->getRelativePath($filePath);
                
                $formattedTraces[] = [
                    'index' => (int)$matches[1],
                    'file' => $filePath,
                    'fileName' => $fileName,
                    'relativePath' => $relativePath,
                    'line' => $lineNumber,
                    'function' => $function,
                    'isProjectFile' => $this->isProjectFile($filePath),
                ];
            } else {
                // 如果解析失败，保留原始格式
                $formattedTraces[] = [
                    'index' => $index,
                    'file' => null,
                    'fileName' => null,
                    'relativePath' => null,
                    'line' => null,
                    'function' => $line,
                    'isProjectFile' => false,
                ];
            }
        }
        
        return $formattedTraces;
    }

    /**
     * 获取相对路径
     */
    private function getRelativePath(string $filePath): string
    {
        $projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
        
        if (strpos($filePath, $projectRoot) === 0) {
            return ltrim(str_replace($projectRoot, '', $filePath), '/');
        }
        
        return $filePath;
    }

    /**
     * 判断是否为项目文件
     */
    private function isProjectFile(string $filePath): bool
    {
        $projectRoot = dirname(dirname(dirname(dirname(__DIR__))));
        return strpos($filePath, $projectRoot) === 0;
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