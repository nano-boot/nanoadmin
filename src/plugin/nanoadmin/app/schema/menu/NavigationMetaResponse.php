<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

#[OA\Schema(title: '导航路由 Meta', description: '动态路由注册所需的稳定 meta 字段')]
class NavigationMetaResponse extends ResponseSchema
{
    #[OA\Property(description: '菜单标题', type: 'string', example: '系统管理')]
    public string $title = '';

    #[OA\Property(description: 'Iconify/Lucide 图标名称', type: 'string', example: 'ri:settings-line')]
    public string $icon = '';

    #[OA\Property(description: '是否缓存页面', type: 'boolean', example: true)]
    public bool $keepAlive = true;

    #[OA\Property(description: '是否隐藏菜单；隐藏节点仍保留在 routes', type: 'boolean', example: false)]
    public bool $isHide = false;

    #[OA\Property(description: '是否隐藏标签页', type: 'boolean', example: false)]
    public bool $isHideTab = false;

    #[OA\Property(description: '是否固定标签页', type: 'boolean', example: false)]
    public bool $fixedTab = false;

    #[OA\Property(description: '是否全屏显示', type: 'boolean', example: false)]
    public bool $isFullPage = false;

    #[OA\Property(description: '是否显示徽标', type: 'boolean', example: false)]
    public bool $showBadge = false;

    #[OA\Property(description: '文字徽标内容', type: 'string', example: '')]
    public string $showTextBadge = '';

    #[OA\Property(description: '页面权限标识', type: 'string', example: '')]
    public string $permission = '';

    #[OA\Property(description: '允许访问的角色编码', type: 'array', items: new OA\Items(type: 'string'))]
    public array $roles = [];

    #[OA\Property(description: '页面按钮权限', type: 'array', items: new OA\Items(ref: '#/components/schemas/NavigationAuthItemResponse'))]
    public array $authList = [];

    #[OA\Property(description: '外链或 iframe 地址', type: 'string', example: 'https://example.com/docs')]
    public string $link = '';

    #[OA\Property(description: '是否以内嵌 iframe 打开 link', type: 'boolean', example: false)]
    public bool $isIframe = false;

    #[OA\Property(description: '当前路由希望激活的目标菜单完整路径', type: 'string', example: '/system/file')]
    public string $activePath = '';
}
