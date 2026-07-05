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

        // 排序：静态路由优先于动态路由，长路径优先于短路径
        // FastRoute 要求静态路由必须先于参数路由注册
        uksort($pathGroups, function ($a, $b) {
            $aHasParams = str_contains($a, '{');
            $bHasParams = str_contains($b, '{');
            // 静态路由优先
            if ($aHasParams !== $bHasParams) {
                return $aHasParams ? 1 : -1;
            }
            // 长度相同则按原始顺序
            return 0;
        });

        foreach ($pathGroups as $path => $methodMap) {
            // 类级中间件作为兜底默认值
            $groupMiddleware = $defaultMiddleware ?? $classMiddleware;

            // 探测是否存在方法级中间件覆盖。
            // 有覆盖时，路径下不同 method 可能需要不同的中间件，无法整组共享 group middleware，
            // 必须逐 method 注册并显式指定 middleware。
            $hasOverride = false;
            foreach ($methodMap as $op) {
                if (($op['middleware'] ?? null) !== null) {
                    $hasOverride = true;
                    break;
                }
            }

            if (!$hasOverride) {
                // 无方法级覆盖：保持原行为，整组挂中间件
                $routeGroup = Route::group($path, function () use ($methodMap, $class) {
                    foreach ($methodMap as $method => $op) {
                        $httpMethod = strtolower($method);
                        Route::$httpMethod('', [$class, $op['action']]);
                    }
                });
                if ($groupMiddleware) {
                    $routeGroup->middleware($groupMiddleware);
                }
                continue;
            }

            // 有方法级覆盖：逐 method 注册到同一 path 下
            // webman Route::group 的中间件无法在子 method 上"清空"，
            // 因此这里用 Route::xx(path, action)->middleware(...) 单独注册，
            // 而不再套 Route::group（group 中间件会与 method 冲突）。
            foreach ($methodMap as $method => $op) {
                $httpMethod = strtolower($method);
                $route = Route::$httpMethod($path, [$class, $op['action']]);

                $override = $op['middleware'] ?? null;
                if ($override !== null) {
                    // 方法级显式：merge 类级 + 方法级；
                    // 方法级 [] = 完全覆盖（即清空类级中间件）
                    $merged = array_merge($groupMiddleware, $override);
                    if (!empty($merged)) {
                        $route->middleware($merged);
                    }
                } elseif ($groupMiddleware) {
                    $route->middleware($groupMiddleware);
                }
            }
        }
    }

    /**
     * @return array<int, array{path: string, method: string, action: string, middleware?: array}>
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
                $op = [
                    'path' => $path,
                    'method' => $methodType,
                    'action' => $method->getName(),
                ];
                // 方法级 #[Middleware(...)] 覆盖：返回 null 表示"未声明"，空数组 [] 表示"清空"
                $methodMw = $this->extractMiddleware($method->getAttributes());
                if ($methodMw !== null) {
                    $op['middleware'] = $methodMw;
                }
                $operations[] = $op;
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

    /**
     * 提取 #[Middleware(...)] 注解里的中间件列表。
     *
     * 返回值语义：
     *  - null：未声明（调用方走默认 / 类级中间件）
     *  - array：声明后的中间件列表，空数组 = "完全无中间件"（覆盖类级）
     *
     * @return array|null
     */
    private function extractMiddleware(array $attributes): ?array
    {
        foreach ($attributes as $attr) {
            if ($attr->getName() === 'support\\annotation\\Middleware') {
                $args = $attr->getArguments();
                if (empty($args)) {
                    // #[Middleware] 不带参数：视为空数组（完全覆盖）
                    return [];
                }
                $first = $args[0];
                // #[Middleware([A::class, B::class])] -> 数组
                if (is_array($first)) {
                    return array_values(array_filter($first, 'is_string'));
                }
                // #[Middleware(A::class, B::class)] -> 多个字符串参数
                return array_values(array_filter($args, 'is_string'));
            }
        }
        return null;
    }
}
