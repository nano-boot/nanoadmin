<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

#[OA\Schema(
    title: '版本化导航响应',
    description: '由同一棵权限菜单树生成的 routes + sidebar 稳定导航契约 v1'
)]
class NavigationResponse extends ResponseSchema
{
    #[OA\Property(description: '导航契约版本', type: 'string', enum: ['v1'], example: 'v1')]
    public string $schemaVersion = 'v1';

    #[OA\Property(description: '当前权限与菜单内容的 SHA-256 指纹', type: 'string', example: 'b7a3d1...')]
    public string $fingerprint = '';

    #[OA\Property(description: '首个可访问的内部页面；没有菜单时为空字符串', type: 'string', example: '/dashboard/console')]
    public string $homePath = '';

    #[OA\Property(description: '用于动态路由注册的完整路由树（隐藏路由仍保留）', type: 'array', items: new OA\Items(ref: '#/components/schemas/NavigationRouteResponse'))]
    public array $routes = [];

    #[OA\Property(description: '已过滤隐藏分支的 Sidebar 分组', type: 'array', items: new OA\Items(ref: '#/components/schemas/SidebarGroupResponse'))]
    public array $sidebarGroups = [];
}
