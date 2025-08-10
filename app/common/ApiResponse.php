<?php

namespace plugin\theadmin\app\common;

/**
 * API响应格式化类
 */
class ApiResponse
{
    /**
     * 成功响应
     */
    public static function success($data = null, string $message = '操作成功'): array
    {
        return [
            'code' => ErrorCode::SUCCESS->value,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ];
    }

    /**
     * 错误响应
     */
    public static function error(ErrorCode|int $code, string $message = '', $data = null): array
    {
        if ($code instanceof ErrorCode) {
            $codeValue = $code->value;
            if (empty($message)) {
                $message = $code->getMessage();
            }
        } else {
            $codeValue = $code;
            if (empty($message)) {
                $message = ErrorCode::getMessageByCode($code);
            }
        }

        return [
            'code' => $codeValue,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ];
    }

    /**
     * 分页响应
     */
    public static function paginate(array $paginateData, string $message = '获取成功'): array
    {
        return [
            'code' => ErrorCode::SUCCESS->value,
            'message' => $message,
            'data' => [
                'list' => $paginateData['data'],
                'pagination' => [
                    'total' => $paginateData['total'],
                    'per_page' => $paginateData['per_page'],
                    'current_page' => $paginateData['current_page'],
                    'last_page' => $paginateData['last_page'],
                ],
            ],
            'timestamp' => time(),
        ];
    }

    /**
     * 列表响应
     */
    public static function list(array $data, string $message = '获取成功'): array
    {
        return [
            'code' => ErrorCode::SUCCESS->value,
            'message' => $message,
            'data' => [
                'list' => $data,
                'total' => count($data),
            ],
            'timestamp' => time(),
        ];
    }

    /**
     * 创建成功响应
     */
    public static function created($data = null, string $message = '创建成功'): array
    {
        return self::success($data, $message);
    }

    /**
     * 更新成功响应
     */
    public static function updated($data = null, string $message = '更新成功'): array
    {
        return self::success($data, $message);
    }

    /**
     * 删除成功响应
     */
    public static function deleted(string $message = '删除成功'): array
    {
        return self::success(null, $message);
    }

    /**
     * 参数错误响应
     */
    public static function parameterError(string $message = '参数错误', $data = null): array
    {
        return self::error(ErrorCode::PARAMETER_ERROR, $message, $data);
    }

    /**
     * 未授权响应
     */
    public static function unauthorized(string $message = '未授权访问'): array
    {
        return self::error(ErrorCode::UNAUTHORIZED, $message);
    }

    /**
     * 权限不足响应
     */
    public static function forbidden(string $message = '权限不足'): array
    {
        return self::error(ErrorCode::FORBIDDEN, $message);
    }

    /**
     * 资源不存在响应
     */
    public static function notFound(string $message = '资源不存在'): array
    {
        return self::error(ErrorCode::NOT_FOUND, $message);
    }

    /**
     * 系统错误响应
     */
    public static function systemError(string $message = '系统错误'): array
    {
        return self::error(ErrorCode::SYSTEM_ERROR, $message);
    }
}