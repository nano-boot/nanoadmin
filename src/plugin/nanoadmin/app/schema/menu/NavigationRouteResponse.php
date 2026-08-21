<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

#[OA\Schema(title: '导航路由节点', description: 'versioned navigation routes 节点')]
class NavigationRouteResponse extends ResponseSchema
{
    #[OA\Property(type: 'integer', example: 1)]
    public int $id = 0;

    #[OA\Property(type: 'string', example: 'system')]
    public string $name = '';

    #[OA\Property(type: 'string', example: '/system')]
    public string $path = '';

    #[OA\Property(type: 'string', example: 'Layout')]
    public string $component = '';

    #[OA\Property(type: 'string', example: '')]
    public string $redirect = '';

    #[OA\Property(type: 'string', enum: ['page', 'directory', 'external', 'iframe'], example: 'directory')]
    public string $kind = 'directory';

    #[OA\Property(type: 'string', example: '/system/user')]
    public string $defaultUrl = '';

    #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
    public array $matchPaths = [];

    #[OA\Property(type: 'object', additionalProperties: true)]
    public array $meta = [];

    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/NavigationRouteResponse'))]
    public array $children = [];
}

