<?php

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\ResponseSchema;

/**
 * 菜单响应结构
 *
 * 用于菜单详情、菜单树节点等接口的 data 字段。
 */
#[OA\Schema(title: '菜单', description: '菜单响应结构')]
class MenuResponse extends ResponseSchema
{
    #[OA\Property(description: '菜单ID', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '父菜单ID', type: 'integer', example: 0)]
    public int $parent_id = 0;

    #[OA\Property(description: '菜单名称', type: 'string', example: '用户管理')]
    public string $name = '';

    #[OA\Property(description: '路由路径', type: 'string', example: '/system/user')]
    public string $path = '';

    #[OA\Property(description: '组件路径', type: 'string', example: 'system/user/index')]
    public string $component = '';

    #[OA\Property(description: '重定向路径', type: 'string', example: '')]
    public string $redirect = '';

    #[OA\Property(description: '菜单图标', type: 'string', example: 'icon-user')]
    public string $icon = '';

    #[OA\Property(description: '菜单类型：D=目录 M=菜单 B=按钮 L=外链 I=内嵌', type: 'string', example: 'M')]
    public string $type = 'M';

    #[OA\Property(description: '菜单类型文本', type: 'string', example: '菜单')]
    public string $type_text = '';

    #[OA\Property(description: '权限标识', type: 'string', example: 'system:user:list')]
    public string $permission = '';

    #[OA\Property(description: '是否隐藏', type: 'boolean', example: false)]
    public bool $hide = false;

    #[OA\Property(description: '是否缓存', type: 'boolean', example: true)]
    public bool $keepAlive = true;

    #[OA\Property(description: '是否全屏显示', type: 'boolean', example: false)]
    public bool $isFullPage = false;

    #[OA\Property(description: '外链地址', type: 'string', example: '')]
    public string $link = '';

    #[OA\Property(description: '是否内嵌', type: 'boolean', example: false)]
    public bool $iframe = false;

    #[OA\Property(description: '是否显示徽章', type: 'boolean', example: false)]
    public bool $showBadge = false;

    #[OA\Property(description: '徽章文本', type: 'string', example: '')]
    public string $badgeText = '';

    #[OA\Property(description: '激活菜单路径', type: 'string', example: '')]
    public string $activePath = '';

    #[OA\Property(description: '状态（true启用 false禁用）', type: 'boolean', example: true)]
    public bool $status = true;

    #[OA\Property(description: '排序值', type: 'integer', example: 100)]
    public int $sort = 100;

    #[OA\Property(description: '创建时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $createdAt = '';

    #[OA\Property(description: '更新时间', type: 'string', example: '2025-01-01 12:00:00')]
    public string $updatedAt = '';
}
