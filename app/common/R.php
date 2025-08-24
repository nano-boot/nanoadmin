<?php

namespace plugin\theadmin\app\common;

use support\Response;

/**
 * API响应格式化类
 */
class R
{
    /**
     * 成功响应
     */
    public static function success($data = null, string $msg = '操作成功'): Response
    {
        return json([
            'code' => Code::SUCCESS->value,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time(),
        ]) ;
    }

    public static function ok($msg = '操作成功', $data = null): Response
    {
        return json([
            'code' => Code::SUCCESS->value,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time(),
        ]) ;
    }

    /**
     * @param $data
     * @param string $msg
     * @param Code|int $code
     * @return Response
     */
    public static function data($data, string $msg = '操作成功', Code|int $code = Code::SUCCESS): Response
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time(),
        ]) ;
    }

    /**
     * 错误响应
     */
    public static function error(Code|string $e , int $code = 40000, $data = null): Response
    {
        $msg = $e;
        if ($e instanceof Code) {
            $msg = $e->getMessage();
            $code = $e->value;
        }
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time(),
        ]) ;
    }

    /**
     * 分页响应
     */
    public static function paginate(array $paginateData, string $msg = '获取成功'): Response
    {
        return json([
            'code' => Code::SUCCESS->value,
            'msg' => $msg,
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
        ]) ;
    }

    /**
     * 列表响应
     */
    public static function list(array $data, string $msg = '获取成功'): Response
    {
        return json([
            'code' => Code::SUCCESS->value,
            'msg' => $msg,
            'data' => [
                'list' => $data,
                'total' => count($data),
            ],
            'timestamp' => time(),
        ]) ;
    }

    /**
     * 创建成功响应
     */
    public static function created($data = null, string $msg = '创建成功'): Response
    {
        return self::data($data, $msg);
    }

    /**
     * 更新成功响应
     */
    public static function updated($data = null, string $msg = '更新成功'): Response
    {
        return self::data($data, $msg);
    }

    /**
     * 删除成功响应
     */
    public static function deleted(string $msg = '删除成功'): Response
    {
        return self::success(null, $msg);
    }

    /**
     * 参数错误响应
     */
    public static function parameterError(string $msg = '参数错误', $data = null): Response
    {
        return self::error(Code::PARAMETER_ERROR, $msg, $data);
    }

    /**
     * 未授权响应
     */
    public static function unauthorized(string $msg = '未授权访问'): Response
    {
        return self::error(Code::UNAUTHORIZED, $msg);
    }

    /**
     * 权限不足响应
     */
    public static function forbidden(string $msg = '权限不足'): Response
    {
        return self::error(Code::FORBIDDEN, $msg);
    }

    /**
     * 资源不存在响应
     */
    public static function notFound(string $msg = '资源不存在'): Response
    {
        return self::error(Code::NOT_FOUND, $msg);
    }

    /**
     * 系统错误响应
     */
    public static function systemError(string $msg = '系统错误'): Response
    {
        return self::error(Code::SYSTEM_ERROR, $msg);
    }
}