<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 请求入口中间件 
 *
 * 在请求链最早阶段实例化控制器对象，挂到 $request->controllerObject。
 * 后续中间件（PermissionMiddleware）直接调用，避免重复反射，提高性能。
 *
 * 设计参考：likeadmin_php
 */
class InitMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $controller = $request->controller ?? '';
        $action = $request->action ?? '';

        if ($controller && class_exists($controller)) {
            try {
                $controllerInstance = new $controller();
                $request->controllerObject = $controllerInstance;
            } catch (\Throwable $e) {
                $request->controllerObject = null;
            }
        } else {
            $request->controllerObject = null;
        }

        return $handler($request);
    }
}
