<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

#[OA\Schema(title: '导航路由节点', description: 'versioned navigation routes 节点')]
class NavigationRouteResponse extends ResponseSchema
{
    #[OA\Property(description: '菜单 ID', type: 'integer', example: 3)]
    public int $id = 0;

    #[OA\Property(description: '路由名称', type: 'string', example: '系统管理')]
    public string $name = '';

    #[OA\Property(description: '规范化后的完整路由路径', type: 'string', example: '/system')]
    public string $path = '';

    #[OA\Property(description: '前端组件标识或路径', type: 'string', example: '/system/file')]
    public string $component = '';

    #[OA\Property(description: '规范化后的重定向路径', type: 'string', example: '/system/file')]
    public string $redirect = '';

    #[OA\Property(description: '导航节点类型', type: 'string', enum: ['page', 'directory', 'external', 'iframe'], example: 'directory')]
    public string $kind = 'directory';

    #[OA\Property(description: '节点默认跳转地址；外链/iframe 为 link 地址', type: 'string', example: '/system/file')]
    public string $defaultUrl = '';

    #[OA\Property(description: '用于 Sidebar 激活匹配的节点自身路径', type: 'array', items: new OA\Items(type: 'string'), example: ['/system'])]
    public array $matchPaths = [];

    #[OA\Property(ref: '#/components/schemas/NavigationMetaResponse')]
    public array $meta = [];

    #[OA\Property(description: '子路由', type: 'array', items: new OA\Items(ref: '#/components/schemas/NavigationRouteResponse'))]
    public array $children = [];
}
