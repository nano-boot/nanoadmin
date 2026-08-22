<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

#[OA\Schema(title: '导航按钮权限', description: '路由携带的按钮权限项')]
class NavigationAuthItemResponse extends ResponseSchema
{
    #[OA\Property(description: '按钮标题', type: 'string', example: '文件列表')]
    public string $title = '';

    #[OA\Property(description: '按钮权限码', type: 'string', example: 'sys:file:list')]
    public string $authMark = '';
}
