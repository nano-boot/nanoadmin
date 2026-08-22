<?php

declare(strict_types=1);

use OpenApi\Attributes\Property;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\schema\menu\NavigationResponse;
use plugin\nanoadmin\app\schema\menu\NavigationRouteResponse;
use plugin\nanoadmin\app\service\MenuService;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

final class MenuNavigationContractTest
{
    private MenuService $service;

    public function __construct()
    {
        $this->service = (new ReflectionClass(MenuService::class))->newInstanceWithoutConstructor();
    }

    public function run(): void
    {
        $routes = $this->invoke('normalizeNavigationRoutes', [[
            [
                'id' => 3,
                'name' => '系统管理',
                'path' => '/system',
                'component' => '/index/index',
                'redirect' => 'file',
                'meta' => ['title' => '系统管理', 'icon' => 'ri:settings-line'],
                'children' => [
                    [
                        'id' => 300,
                        'name' => '文件管理',
                        'path' => 'file',
                        'component' => '/system/file',
                        'meta' => ['title' => '文件管理'],
                    ],
                    [
                        'id' => 301,
                        'name' => '隐藏目录',
                        'path' => 'hidden',
                        'component' => '',
                        'meta' => ['title' => '隐藏目录', 'isHide' => 1],
                        'children' => [[
                            'id' => 302,
                            'name' => '隐藏目录子页面',
                            'path' => 'child',
                            'component' => '/system/hidden/child',
                            'meta' => ['title' => '隐藏目录子页面'],
                        ]],
                    ],
                    [
                        'id' => 303,
                        'name' => '外部文档',
                        'path' => 'docs',
                        'component' => 'IframeView',
                        'meta' => [
                            'title' => '外部文档',
                            'link' => 'https://example.com/docs',
                            'isIframe' => false,
                        ],
                    ],
                    [
                        'id' => 304,
                        'name' => '内嵌文档',
                        'path' => 'openapi',
                        'component' => 'IframeView',
                        'meta' => [
                            'title' => '内嵌文档',
                            'link' => 'https://example.com/openapi',
                            'isIframe' => true,
                        ],
                    ],
                    [
                        'id' => 305,
                        'name' => '日志管理',
                        'path' => 'log',
                        'component' => '',
                        'meta' => ['title' => '日志管理'],
                        'children' => [[
                            'id' => 306,
                            'name' => '登录日志',
                            'path' => 'login',
                            'component' => '/system/log/login',
                            'meta' => ['title' => '登录日志'],
                        ]],
                    ],
                ],
            ],
        ]]);

        $system = $routes[0];
        $this->assertSame('/system/file', $system['redirect'], '相对 redirect 应基于当前目录生成完整路径');
        $this->assertSame('/system/file', $system['defaultUrl'], '目录默认地址应使用完整 redirect');
        $this->assertSame('directory', $system['kind'], '含子节点的菜单应标记为目录');
        $this->assertSame('/system/file', $system['children'][0]['path'], '二级相对路由应拼接父路径');
        $this->assertSame('/system/hidden/child', $system['children'][1]['children'][0]['path'], '任意深度相对路由都应生成完整路径');
        $this->assertSame('/system/log/login', $system['children'][4]['children'][0]['path'], '三级路由应生成完整路径');
        $this->assertSame(['/system/file'], $system['children'][0]['matchPaths'], 'matchPaths 只包含节点自身完整路径');

        $fileMeta = $system['children'][0]['meta'];
        $this->assertSame('', $fileMeta['link'], '普通页面 meta.link 应保持字符串零值');
        $this->assertSame(false, $fileMeta['isIframe'], '普通页面 meta.isIframe 应保持布尔零值');
        $this->assertSame([], $fileMeta['roles'], '普通页面 meta.roles 应保持数组零值');
        $this->assertSame([], $fileMeta['authList'], '普通页面 meta.authList 应保持数组零值');

        $sidebar = $this->invoke('buildSidebarItems', [$routes]);
        $this->assertSame(1, count($sidebar), 'Sidebar 应保留顶级目录');
        $this->assertSame(4, count($sidebar[0]['children']), '隐藏父节点的整个分支都应从 Sidebar 移除');
        $this->assertSame(
            ['文件管理', '外部文档', '内嵌文档', '日志管理'],
            array_column($sidebar[0]['children'], 'title'),
            '隐藏分支的子节点不能提升到上一级'
        );

        $external = $sidebar[0]['children'][1];
        $this->assertSame('/system/docs', $external['path'], '外链仍应保留完整内部路由路径');
        $this->assertSame('https://example.com/docs', $external['defaultUrl'], '外链默认地址应使用 link');
        $this->assertSame('external', $external['kind'], '外链 kind 应正确');
        $this->assertSame(true, $external['external'], '外链标记应正确');
        $this->assertSame(false, $external['iframe'], '外链不应误标为 iframe');

        $iframe = $sidebar[0]['children'][2];
        $this->assertSame('/system/openapi', $iframe['path'], 'iframe 应使用完整内部路由路径承载页面');
        $this->assertSame('https://example.com/openapi', $iframe['defaultUrl'], 'iframe 应保留 link 地址');
        $this->assertSame('iframe', $iframe['kind'], 'iframe kind 应正确');
        $this->assertSame(false, $iframe['external'], 'iframe 不应误标为外链');
        $this->assertSame(true, $iframe['iframe'], 'iframe 标记应正确');

        $this->assertSame('/system/file', $this->invoke('findFirstNavigationPath', [$routes]), '首页应选择首个可见内部页面');
        $this->assertSame(
            '',
            $this->invoke('findFirstNavigationPath', [[
                $system['children'][1],
                $system['children'][2],
                $system['children'][3],
            ]]),
            '隐藏项、外链和 iframe 都不能成为首页'
        );

        $metaProperty = new ReflectionProperty(NavigationRouteResponse::class, 'meta');
        $metaAttribute = $metaProperty->getAttributes(Property::class)[0]->newInstance();
        $this->assertSame(
            '#/components/schemas/NavigationMetaResponse',
            $metaAttribute->ref,
            'OpenAPI route meta 应引用明确 DTO'
        );

        $dataResponse = new DataResponse(schema: NavigationResponse::class);
        $properties = (new ReflectionMethod($dataResponse, 'extractPropertiesFromSchema'))
            ->invoke($dataResponse, NavigationResponse::class);
        $this->assertSame(
            ['schemaVersion', 'fingerprint', 'homePath', 'routes', 'sidebarGroups'],
            array_map(static fn (Property $property): string => (string)$property->property, $properties),
            'OpenAPI data DTO 不应暴露 ResponseSchema 内部字段'
        );
    }

    /** @param array<int,mixed> $arguments */
    private function invoke(string $method, array $arguments): mixed
    {
        return (new ReflectionMethod(MenuService::class, $method))->invokeArgs($this->service, $arguments);
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                "%s\nExpected: %s\nActual:   %s",
                $message,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }
}

try {
    (new MenuNavigationContractTest())->run();
    fwrite(STDOUT, "Menu navigation contract tests passed.\n");
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
