<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

#[OA\Schema(title: '版本化导航响应', description: 'routes + sidebar 稳定导航契约 v1')]
class NavigationResponse extends ResponseSchema
{
    #[OA\Property(type: 'string', enum: ['v1'], example: 'v1')]
    public string $schemaVersion = 'v1';

    #[OA\Property(type: 'string', example: 'sha256:...')]
    public string $fingerprint = '';

    #[OA\Property(type: 'string', example: '/dashboard')]
    public string $homePath = '';

    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/NavigationRouteResponse'))]
    public array $routes = [];

    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/SidebarGroupResponse'))]
    public array $sidebarGroups = [];
}

