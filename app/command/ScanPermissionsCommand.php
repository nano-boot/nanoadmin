<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Finder\Finder;
use Webman\Route;
use plugin\nanoadmin\app\attribute\Permission;
use plugin\nanoadmin\app\attribute\AllowAnonymous;
use plugin\nanoadmin\app\common\PermissionInferrer;

/**
 * 扫描控制器，列出"未登记权限的接口"（Phase 3 新增）
 *
 * 设计来源：authorization-refactoring-plan.md §3.2 / §3.3
 *
 * 用法：
 *   php webman scan:permissions            详细报告（默认）
 *   php webman scan:permissions --missing  仅列出未登记的接口
 *   php webman scan:permissions --check    CI 模式：有缺失时 exit 1，无缺失 exit 0
 *   php webman scan:permissions --json     输出 JSON 报告（给脚本消费）
 *
 * 判定口径：
 *   满足以下任一条件视为"已登记权限"：
 *     1. 方法级 #[Permission] 注解
 *     2. 类级 #[Permission] 注解（兜底）
 *     3. #[AllowAnonymous(requirePermission: false)] 注解（声明免权限）
 *     4. PermissionInferrer 能从 (method, path) 推断出权限码
 *
 *   都不满足 → 未登记（fail-closed 中间件会拒绝访问）。
 *
 * 与 PermissionMiddleware 一致性：
 *   扫描逻辑与 PermissionMiddleware::getRequiredPermission 完全对齐，
 *   任何一个能在运行时拿到权限码的接口，都不会被报告为缺失。
 *
 * 注意：
 *   - 不依赖 Webman\Route（运行时路由），直接扫控制器文件 + 反射，
 *     因此可在 CI / 单元测试中独立运行，不要求 webman bootstrap。
 *   - 不连数据库，所以无法报告"权限码在数据库中缺失"，仅做代码层静态检查。
 */
#[AsCommand(
    name: 'scan:permissions',
    description: '扫描控制器，列出未登记权限的接口（CI 防退化）'
)]
class ScanPermissionsCommand extends Command
{
    /** @var string 默认扫描的控制器目录（相对 base_path 或绝对路径） */
    private const DEFAULT_CONTROLLER_DIR = 'plugin/nanoadmin/app/controller';

    /** @var string 默认命名空间前缀（与扫描目录对应） */
    private const DEFAULT_NAMESPACE = 'plugin\\nanoadmin\\app\\controller\\';

    /**
     * 不需要权限校验的路由前缀（与 PermissionMiddleware::exclude_routes 对齐）。
     * 业务上写在这里的接口，即使没 #[Permission] 也不算缺失。
     *
     * 来源：plugin/nanoadmin/config/nanoadmin.php 中的 no_permission_routes 池。
     * 这里写死以保证扫描器独立运行（不依赖 webman bootstrap）。
     *
     * @var string[]
     */
    private const EXCLUDE_PREFIXES = [
        '/',
        '/install',
        '/sys/install',
        '/sys/openapi',
        '/sys/openapi/doc',
        '/sys/auth/info',
        '/sys/auth/permissions',
        '/sys/auth/menus',
        '/sys/menu/route',
        '/sys/admin/password',
        '/sys/admin/info',
        '/sys/auth/login',
        '/sys/auth/refresh',
        '/sys/auth/captcha',
        '/sys/auth/check',
        '/sys/auth/logout',
    ];

    /**
     * OA 操作注解的完整类名列表（与 OpenApiRouteRegister 对齐）。
     * @var string[]
     */
    private const OA_OPERATION_CLASSES = [
        'OpenApi\\Attributes\\Get',
        'OpenApi\\Attributes\\Post',
        'OpenApi\\Attributes\\Put',
        'OpenApi\\Attributes\\Delete',
        'OpenApi\\Attributes\\Patch',
        'OpenApi\\Attributes\\Options',
        'OpenApi\\Attributes\\Head',
    ];

    protected function configure(): void
    {
        $this
            ->setName('scan:permissions')
            ->setDescription('扫描控制器，列出未登记权限的接口（CI 防退化）')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, '控制器目录（相对 base_path 或绝对路径）', self::DEFAULT_CONTROLLER_DIR)
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, '控制器命名空间前缀', self::DEFAULT_NAMESPACE)
            ->addOption('missing', 'm', InputOption::VALUE_NONE, '仅输出未登记权限的接口')
            ->addOption('check', 'c', InputOption::VALUE_NONE, 'CI 模式：有缺失时 exit 1')
            ->addOption('json', 'j', InputOption::VALUE_NONE, '输出 JSON 报告');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scanPath = $this->resolveScanPath((string) $input->getOption('path'));
        if (!is_dir($scanPath)) {
            $output->writeln("<error>✗ 扫描目录不存在: {$scanPath}</error>");
            return self::FAILURE;
        }

        $namespace = rtrim((string) $input->getOption('namespace'), '\\') . '\\';

        $routes = $this->scanControllers($scanPath, $namespace);

        // 同时把 webman 已注册的运行时路由也加入扫描（如有），覆盖手写 Route::group 的场景
        $runtimeRoutes = $this->scanRuntimeRoutes();
        $routes = array_merge($routes, $runtimeRoutes);

        $report = $this->buildReport($routes);

        if ($input->getOption('json')) {
            return $this->outputJson($output, $report);
        }

        $this->outputTable($output, $report, (bool) $input->getOption('missing'));

        $missingCount = count($report['missing']);
        $totalCount   = count($report['all']);

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>扫描完成：总计 %d 个接口，未登记权限 %d 个。</info>',
            $totalCount,
            $missingCount
        ));

        if ($missingCount > 0) {
            $output->writeln('<comment>修复建议：为缺失接口添加 #[Permission] 注解，或在 no_permission_routes 池中放行。</comment>');

            if ($input->getOption('check')) {
                $output->writeln('<error>CI 检查失败：存在未登记权限的接口</error>');
                return self::FAILURE;
            }
        } else {
            $output->writeln('<info>✓ 所有接口均已登记权限或可自动推断</info>');
        }

        return self::SUCCESS;
    }

    /**
     * 解析扫描目录
     */
    private function resolveScanPath(string $inputPath): string
    {
        if (self::isAbsolutePath($inputPath)) {
            return $inputPath;
        }
        // 兼容 webman 运行上下文
        if (function_exists('base_path')) {
            return rtrim((string) base_path(), '/\\') . DIRECTORY_SEPARATOR . trim($inputPath, '/\\');
        }
        return getcwd() . DIRECTORY_SEPARATOR . trim($inputPath, '/\\');
    }

    private static function isAbsolutePath(string $path): bool
    {
        return $path !== '' && (
            $path[0] === '/' || $path[0] === '\\' ||
            (strlen($path) >= 2 && $path[1] === ':')
        );
    }

    /**
     * 扫描控制器目录，返回所有路由的元数据
     *
     * @return array<int, array{method:string, path:string, controller:string, action:string}>
     */
    private function scanControllers(string $scanPath, string $namespace): array
    {
        $files = Finder::create()->files()->name('*.php')->in($scanPath);

        $routes = [];
        foreach ($files as $file) {
            $relative = str_replace(['/', '\\'], '\\', ltrim(substr($file->getPathname(), strlen($scanPath)), '/\\'));
            $relative = ltrim($relative, '\\');
            $class = rtrim($namespace . $relative, '.php');
            if (!class_exists($class)) {
                continue;
            }
            foreach ($this->collectOperations($class) as $op) {
                $routes[] = $op;
            }
        }
        return $routes;
    }

    /**
     * 收集控制器类上所有 OA 操作注解对应的路由
     *
     * @return array<int, array{method:string, path:string, controller:string, action:string}>
     */
    private function collectOperations(string $class): array
    {
        $ref = new \ReflectionClass($class);
        $operations = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attr) {
                $attrName = $attr->getName();
                if (!in_array($attrName, self::OA_OPERATION_CLASSES, true)) {
                    continue;
                }
                /** @var \OpenApi\Attributes\Operation $oa */
                $oa = $attr->newInstance();
                $path = $oa->path ?? '';
                if ($path === '' || \OpenApi\Generator::isDefault($path)) {
                    continue;
                }
                $methodType = $this->getOperationMethod($attrName);
                if ($methodType === null) {
                    continue;
                }
                $operations[] = [
                    'method'     => strtoupper($methodType),
                    'path'       => $path,
                    'controller' => $class,
                    'action'     => $method->getName(),
                ];
            }
        }
        return $operations;
    }

    private function getOperationMethod(string $oaClassName): ?string
    {
        $short = substr($oaClassName, strrpos($oaClassName, '\\') + 1);
        return [
            'Get' => 'get', 'Post' => 'post', 'Put' => 'put',
            'Delete' => 'delete', 'Patch' => 'patch',
            'Options' => 'options', 'Head' => 'head',
        ][$short] ?? null;
    }

    /**
     * 从 Webman 运行时路由表补充扫描（处理手写 Route::group 的场景）
     *
     * @return array<int, array{method:string, path:string, controller:string, action:string}>
     */
    private function scanRuntimeRoutes(): array
    {
        if (!class_exists(Route::class) || !method_exists(Route::class, 'getRoutes')) {
            return [];
        }

        $routes = [];
        try {
            foreach (Route::getRoutes() as $route) {
                $cb = $route->getCallback();
                if (!is_array($cb) || count($cb) < 2 || !is_string($cb[0]) || !is_string($cb[1])) {
                    continue; // 跳过 Closure / 字符串回调 / 单元素
                }
                foreach ($route->getMethods() as $method) {
                    if (strtoupper($method) === 'ANY' || $method === 'HEAD') {
                        continue;
                    }
                    $routes[] = [
                        'method'     => strtoupper($method),
                        'path'       => $route->getPath(),
                        'controller' => ltrim($cb[0], '\\'),
                        'action'     => $cb[1],
                    ];
                }
            }
        } catch (\Throwable $e) {
            // webman 未 bootstrap 时静默忽略
        }

        return $routes;
    }

    /**
     * 判定一个接口是否"已登记权限"
     *
     * 与 PermissionMiddleware::getRequiredPermission 判定口径一致：
     *   1. 方法级 #[Permission]        ✓
     *   2. 类级 #[Permission]           ✓
     *   3. #[AllowAnonymous(requirePermission: false)] ✓
     *   4. PermissionInferrer 能推断    ✓
     *   5. exclude_routes 前缀命中       ✓
     */
    private function isProtected(array $route): bool
    {
        $controller = $route['controller'];
        $action     = $route['action'];

        if (class_exists($controller)) {
            // 方法级 #[Permission]
            $methodAttrs = $this->getAttributesOfMethod($controller, $action, Permission::class);
            if (!empty($methodAttrs)) {
                return true;
            }
            // 类级 #[Permission]
            if (!empty($this->getAttributesOfClass($controller, Permission::class))) {
                return true;
            }
            // #[AllowAnonymous(requirePermission: false)]
            $anon = $this->getFirstAttribute($controller, $action, AllowAnonymous::class);
            if ($anon !== null && empty($anon['requirePermission'])) {
                return true;
            }
        }

        // 自动推断
        $inferred = PermissionInferrer::infer($route['method'], $route['path']);
        if ($inferred !== null) {
            return true;
        }

        // exclude_routes 前缀
        if ($this->matchesExcludePrefix($route['path'])) {
            return true;
        }

        return false;
    }

    /**
     * 构建扫描报告
     *
     * @param array<int, array{method:string, path:string, controller:string, action:string}> $routes
     * @return array{all: array, missing: array, missing_count: int, total_count: int}
     */
    private function buildReport(array $routes): array
    {
        $all = [];
        $missing = [];
        $seen = [];

        foreach ($routes as $route) {
            $key = $route['method'] . ' ' . $route['path'] . ' ' . $route['controller'] . '::' . $route['action'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $protected = $this->isProtected($route);

            $row = [
                'method'       => $route['method'],
                'path'         => $route['path'],
                'controller'   => $this->shortClass($route['controller']),
                'action'       => $route['action'],
                'permission'   => $this->resolvePermission($route),
                'protected'    => $protected ? '✓' : '✗',
                'source'       => $this->resolveSource($route),
            ];

            $all[] = $row;
            if (!$protected) {
                $missing[] = $row;
            }
        }

        return [
            'all'           => $all,
            'missing'       => $missing,
            'missing_count' => count($missing),
            'total_count'   => count($all),
        ];
    }

    /**
     * 解析出接口对应的权限码（用于报告展示）
     */
    private function resolvePermission(array $route): string
    {
        $controller = $route['controller'];
        $action     = $route['action'];

        if (class_exists($controller)) {
            $methodAttrs = $this->getAttributesOfMethod($controller, $action, Permission::class);
            if (!empty($methodAttrs)) {
                return $methodAttrs[0]['code'];
            }
            $classAttrs = $this->getAttributesOfClass($controller, Permission::class);
            if (!empty($classAttrs)) {
                return $classAttrs[0]['code'];
            }
        }
        $inferred = PermissionInferrer::infer($route['method'], $route['path']);
        return $inferred ?? '(无)';
    }

    /**
     * 解析权限码来源（注解 / 推断 / 免权限）
     */
    private function resolveSource(array $route): string
    {
        $controller = $route['controller'];
        $action     = $route['action'];

        if (class_exists($controller)) {
            if (!empty($this->getAttributesOfMethod($controller, $action, Permission::class))) {
                return 'method';
            }
            if (!empty($this->getAttributesOfClass($controller, Permission::class))) {
                return 'class';
            }
            $anon = $this->getFirstAttribute($controller, $action, AllowAnonymous::class);
            if ($anon !== null && empty($anon['requirePermission'])) {
                return 'allow';
            }
        }
        if ($this->matchesExcludePrefix($route['path'])) {
            return 'exclude';
        }
        if (PermissionInferrer::infer($route['method'], $route['path']) !== null) {
            return 'infer';
        }
        return 'none';
    }

    /**
     * 读取方法级指定 Attribute 列表（含父类追溯）
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAttributesOfMethod(string $controller, string $action, string $attrClass): array
    {
        $current = $controller;
        while ($current && class_exists($current)) {
            if (method_exists($current, $action)) {
                try {
                    $ref = new \ReflectionMethod($current, $action);
                    $result = [];
                    foreach ($ref->getAttributes($attrClass) as $attr) {
                        $result[] = get_object_vars($attr->newInstance());
                    }
                    return $result;
                } catch (\Throwable $e) {
                    return [];
                }
            }
            $current = get_parent_class($current);
        }
        return [];
    }

    /**
     * 读取类级指定 Attribute 列表
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAttributesOfClass(string $controller, string $attrClass): array
    {
        if (!class_exists($controller)) {
            return [];
        }
        try {
            $ref = new \ReflectionClass($controller);
            $result = [];
            foreach ($ref->getAttributes($attrClass) as $attr) {
                $result[] = get_object_vars($attr->newInstance());
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 读取方法级第一个 Attribute（用于 AllowAnonymous）
     *
     * @return array<string, mixed>|null
     */
    private function getFirstAttribute(string $controller, string $action, string $attrClass): ?array
    {
        $list = $this->getAttributesOfMethod($controller, $action, $attrClass);
        return $list[0] ?? null;
    }

    /**
     * 路径前缀命中（与 BaseMiddleware::matchesExcludeRoute 一致）
     *
     * 规则（与运行时中间件完全对齐）：
     *  - 根路径 '/' 精确匹配
     *  - 其他：str_starts_with($path, $route) || $path === $route
     *
     * 注意：运行时中间件不加 '/' 后缀，所以 '/sys/admin' 也会"误匹配" '/sys/admin/password'。
     * 扫描器必须完全对齐这套语义，否则会出现"扫描通过但中间件拒绝 / 扫描失败但中间件放行"的假象。
     */
    private function matchesExcludePrefix(string $path): bool
    {
        $normalized = '/' . ltrim($path, '/');
        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
        }

        foreach (self::EXCLUDE_PREFIXES as $prefix) {
            if ($prefix === '/') {
                if ($normalized === '/') {
                    return true;
                }
                continue;
            }
            if ($normalized === $prefix || str_starts_with($normalized, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 类名短显示
     */
    private function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    /**
     * 输出表格报告
     *
     * @param array{all: array, missing: array} $report
     */
    private function outputTable(OutputInterface $output, array $report, bool $onlyMissing): void
    {
        $rows = $onlyMissing ? $report['missing'] : $report['all'];
        if (empty($rows)) {
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Method', 'Path', 'Controller::Action', 'Permission', 'Source', 'Status']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['method'],
                $row['path'],
                $row['controller'] . '::' . $row['action'],
                $row['permission'],
                $row['source'],
                $row['protected'],
            ]);
        }
        $table->render();
    }

    /**
     * 输出 JSON 报告
     *
     * @param array{all: array, missing: array, missing_count: int, total_count: int} $report
     */
    private function outputJson(OutputInterface $output, array $report): int
    {
        $output->writeln(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return $report['missing_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}