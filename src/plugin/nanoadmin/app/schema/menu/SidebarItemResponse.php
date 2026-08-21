<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

#[OA\Schema(title: 'Sidebar 菜单项', description: 'shadcn Sidebar 稳定菜单节点')]
class SidebarItemResponse extends ResponseSchema
{
    #[OA\Property(type: 'integer', example: 1)]
    public int $id = 0;

    #[OA\Property(type: 'string', example: 'system')]
    public string $key = '';

    #[OA\Property(type: 'string', example: '系统管理')]
    public string $title = '';

    #[OA\Property(type: 'string', example: 'menus.system.title')]
    public string $titleKey = '';

    #[OA\Property(type: 'string', example: 'ri:settings-3-line')]
    public string $icon = '';

    #[OA\Property(type: 'string', example: '/system')]
    public string $path = '';

    #[OA\Property(type: 'string', enum: ['page', 'directory', 'external', 'iframe'])]
    public string $kind = 'page';

    #[OA\Property(type: 'string', example: '')]
    public string $defaultUrl = '';

    #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
    public array $matchPaths = [];

    #[OA\Property(type: 'boolean', example: false)]
    public bool $badge = false;

    #[OA\Property(type: 'string', example: '')]
    public string $badgeText = '';

    #[OA\Property(type: 'boolean', example: false)]
    public bool $hidden = false;

    #[OA\Property(type: 'boolean', example: false)]
    public bool $disabled = false;

    #[OA\Property(type: 'boolean', example: false)]
    public bool $external = false;

    #[OA\Property(type: 'boolean', example: false)]
    public bool $iframe = false;

    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/SidebarItemResponse'))]
    public array $children = [];
}

