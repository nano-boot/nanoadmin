<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\library\swagger;

use OpenApi\Attributes as OA;
use OpenApi\Generator;
use OpenApi\Util;
use Symfony\Component\Finder\Finder;
use Webman\Route;

/**
 * 读取控制器类上的 OA 注解（#[OA\Get / OA\Post / OA\Put / OA\Delete / OA\Patch）
 * 并自动注册为 webman 路由。
 *
 * 使用方式（route.php）：
 *   (new OpenApiRouteRegister())->register([base_path() . '/plugin/nanoadmin/app/controller']);
 *
 * 中间件统一从类级 #[Middleware(...)] 读取（webman 的 support\annotation\Middleware），
 * 不再依赖 OA 注解里的 x[route-middleware]。
 *
 * 来源：早期从 webman-tech/swagger 包里的 RouteAnnotation\Register + Reader 精简而来，
 * 去掉对 webman-tech 的依赖，保留核心功能。
 */
class OpenApiRouteRegister
{
    private string $basePath;

    private string $pluginNamespace = 'plugin\\nanoadmin';

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath ?: base_path();
    }

    /**
     * 扫描 $scanPaths 下的所有 PHP 文件，注册所有 OA 注解路由
     *
     * @param array $scanPaths
     * @param array|null $middleware 统一中间件（null = 从类级 #[Middleware(...)] 读取）
     */
    public function register(array $scanPaths, ?array $middleware = null): void
    {
        $classes = $this->scanControllerClasses($scanPaths);

        foreach ($classes as $class) {
            $this->registerController($class, $middleware);
        }
    }

    private function scanControllerClasses(array $scanPaths): array
    {
        $classes = [];
        foreach ($scanPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $files = Finder::create()->files()->name('*.php')->in($path);
            foreach ($files as $file) {
                $fqcn = $this->filePathToClass($file->getPathname(), $path);
                if ($fqcn && class_exists($fqcn)) {
                    $classes[] = $fqcn;
                }
            }
        }
        return $classes;
    }

    private function filePathToClass(string $filePath, string $scanPath): ?string
    {
        $relative = ltrim(str_replace($scanPath, '', $filePath), '/\\');
        $relative = str_replace(['/', '\\'], '\\', $relative);
        $relative = ltrim($relative, '\\');
        $class = $this->pluginNamespace . '\\app\\controller\\' . $relative;
        $class = rtrim($class, '.php');
        return class_exists($class) ? $class : null;
    }

    private function registerController(string $class, ?array $defaultMiddleware): void
    {
        $ref = new \ReflectionClass($class);

        // 读取类级中间件
        $classMiddleware = $this->extractMiddleware($ref->getAttributes());
        $middleware = $defaultMiddleware ?? $classMiddleware;

        // 收集所有带 #[OA\Get / OA\Post / OA\Put / OA\Delete / OA\Patch] 的 public 方法
        $operations = $this->collectOperations($ref);
        if (!$operations) {
            return;
        }

        // 按 path 分组，相同 path 的不同 method 合并到同一 Route::group
        $pathGroups = [];
        foreach ($operations as $op) {
            $pathGroups[$op['path']][$op['method']] = $op;
        }

        foreach ($pathGroups as $path => $methodMap) {
            $routeGroup = Route::group($path, function () use ($methodMap, $class) {
                foreach ($methodMap as $method => $op) {
                    $httpMethod = strtolower($method);
                    Route::$httpMethod('', [$class, $op['action']]);
                }
            });

            if ($middleware) {
                $routeGroup->middleware($middleware);
            }
        }
    }

    /**
     * @return array<int, array{path: string, method: string, action: string}>
     */
    private function collectOperations(\ReflectionClass $ref): array
    {
        // OA\Get / OA\Post 等的完整类名（不是 OA\Operation）
        $oaOperationClasses = [
            'OpenApi\\Attributes\\Get',
            'OpenApi\\Attributes\\Post',
            'OpenApi\\Attributes\\Put',
            'OpenApi\\Attributes\\Delete',
            'OpenApi\\Attributes\\Patch',
            'OpenApi\\Attributes\\Options',
            'OpenApi\\Attributes\\Head',
        ];

        $operations = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attr) {
                $attrName = $attr->getName();
                // 检查是否是 OA 操作注解
                if (!in_array($attrName, $oaOperationClasses, true)) {
                    continue;
                }
                /** @var \OpenApi\Attributes\Operation $oa */
                $oa = $attr->newInstance();
                $path = $oa->path;
                if (!$path || Generator::isDefault($path)) {
                    continue;
                }
                $methodType = $this->getOperationMethod($attrName);
                if (!$methodType) {
                    continue;
                }
                $operations[] = [
                    'path' => $path,
                    'method' => $methodType,
                    'action' => $method->getName(),
                ];
            }
        }
        return $operations;
    }

    private function getOperationMethod(string $oaClassName): ?string
    {
        $short = substr($oaClassName, strrpos($oaClassName, '\\') + 1);
        return [
            'Get' => 'get',
            'Post' => 'post',
            'Put' => 'put',
            'Delete' => 'delete',
            'Patch' => 'patch',
            'Options' => 'options',
            'Head' => 'head',
        ][$short] ?? null;
    }

    private function extractMiddleware(array $attributes): array
    {
        foreach ($attributes as $attr) {
            if ($attr->getName() === 'support\\annotation\\Middleware') {
                $args = $attr->getArguments();
                if (empty($args)) {
                    return [];
                }
                $first = $args[0];
                // #[Middleware([A::class, B::class])] -> 数组
                if (is_array($first)) {
                    return $first;
                }
                // #[Middleware(A::class, B::class)] -> 多个字符串参数
                return array_values(array_filter($args, 'is_string'));
            }
        }
        return [];
    }
}
