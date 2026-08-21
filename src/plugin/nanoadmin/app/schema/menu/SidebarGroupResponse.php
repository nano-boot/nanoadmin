<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

#[OA\Schema(title: 'Sidebar 菜单组', description: 'shadcn Sidebar 菜单组')]
class SidebarGroupResponse extends ResponseSchema
{
    #[OA\Property(type: 'string', example: 'main')]
    public string $id = '';

    #[OA\Property(type: 'string', example: '')]
    public string $title = '';

    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/SidebarItemResponse'))]
    public array $items = [];
}

