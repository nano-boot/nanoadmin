<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

#[OA\Schema(title: 'Sidebar 菜单项', description: 'shadcn Sidebar 稳定菜单节点')]
class SidebarItemResponse extends ResponseSchema
{
    #[OA\Property(description: '菜单 ID', type: 'integer', example: 3)]
    public int $id = 0;

    #[OA\Property(description: '稳定渲染 key', type: 'string', example: '系统管理')]
    public string $key = '';

    #[OA\Property(description: '可直接显示的标题', type: 'string', example: '系统管理')]
    public string $title = '';

    #[OA\Property(description: '可选 i18n key；当前与 title 同源', type: 'string', example: '系统管理')]
    public string $titleKey = '';

    #[OA\Property(description: 'Iconify/Lucide 图标名称', type: 'string', example: 'ri:settings-line')]
    public string $icon = '';

    #[OA\Property(description: '节点自身的完整内部路由路径', type: 'string', example: '/system')]
    public string $path = '';

    #[OA\Property(description: 'Sidebar 节点类型', type: 'string', enum: ['page', 'directory', 'external', 'iframe'], example: 'directory')]
    public string $kind = 'page';

    #[OA\Property(description: '默认跳转地址', type: 'string', example: '/system/file')]
    public string $defaultUrl = '';

    #[OA\Property(description: '激活匹配路径', type: 'array', items: new OA\Items(type: 'string'), example: ['/system'])]
    public array $matchPaths = [];

    #[OA\Property(description: '是否显示徽标', type: 'boolean', example: false)]
    public bool $badge = false;

    #[OA\Property(description: '文字徽标内容', type: 'string', example: '')]
    public string $badgeText = '';

    #[OA\Property(description: '隐藏标记；隐藏分支已被过滤，返回项固定为 false', type: 'boolean', example: false)]
    public bool $hidden = false;

    #[OA\Property(description: '禁用标记', type: 'boolean', example: false)]
    public bool $disabled = false;

    #[OA\Property(description: '是否在新窗口打开 defaultUrl', type: 'boolean', example: false)]
    public bool $external = false;

    #[OA\Property(description: '是否通过内部 path 打开 iframe 页面', type: 'boolean', example: false)]
    public bool $iframe = false;

    #[OA\Property(description: '可见子菜单；隐藏父节点的整个分支不会被提升', type: 'array', items: new OA\Items(ref: '#/components/schemas/SidebarItemResponse'))]
    public array $children = [];
}
