<?php

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\RequestSchema;

/**
 * 菜单创建/更新请求结构
 *
 * 只做 OpenAPI 文档，校验统一走 MenuValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\menu\MenuValidator
 */
#[OA\Schema(title: '菜单请求', description: '菜单创建/更新请求参数')]
class MenuRequest extends RequestSchema
{
    #[OA\Property(description: '菜单ID（更新时必填）', type: 'integer', format: 'int64', example: 1)]
    public int $id = 0;

    #[OA\Property(description: '父菜单ID（0 表示顶级菜单）', type: 'integer', example: 0)]
    public int $parent_id = 0;

    #[OA\Property(description: '菜单名称', type: 'string', example: '用户管理')]
    public string $name = '';

    #[OA\Property(description: '路由路径', type: 'string', example: '/system/user')]
    public string $path = '';

    #[OA\Property(description: '组件路径', type: 'string', example: 'system/user/index')]
    public string $component = '';

    #[OA\Property(description: '重定向路径', type: 'string', example: '/system/user/list')]
    public string $redirect = '';

    #[OA\Property(description: '菜单图标', type: 'string', example: 'icon-user')]
    public string $icon = '';

    #[OA\Property(description: '菜单类型：D=目录 M=菜单 B=按钮 L=外链 I=内嵌', type: 'string', example: 'M')]
    public string $type = 'M';

    #[OA\Property(description: '权限标识', type: 'string', example: 'system:user:list')]
    public string $permission = '';

    #[OA\Property(description: '是否隐藏', type: 'boolean', example: false)]
    public bool $hidden = false;

    #[OA\Property(description: '是否隐藏标签页', type: 'boolean', example: false)]
    public bool $hide_tab = false;

    #[OA\Property(description: '是否全屏显示', type: 'boolean', example: false)]
    public bool $full_page = false;

    #[OA\Property(description: '是否缓存', type: 'boolean', example: true)]
    public bool $keep_alive = true;

    #[OA\Property(description: '是否固定标签', type: 'boolean', example: false)]
    public bool $fixed_tab = false;

    #[OA\Property(description: '外链地址', type: 'string', example: 'https://example.com')]
    public string $link = '';

    #[OA\Property(description: '是否内嵌', type: 'boolean', example: false)]
    public bool $iframe = false;

    #[OA\Property(description: '是否显示徽章', type: 'boolean', example: false)]
    public bool $show_badge = false;

    #[OA\Property(description: '徽章文本', type: 'string', example: 'New')]
    public string $badge_text = '';

    #[OA\Property(description: '激活菜单路径', type: 'string', example: '/system/user/list')]
    public string $active_path = '';

    #[OA\Property(description: '状态（true启用 false禁用）', type: 'boolean', example: true)]
    public bool $status = true;

    #[OA\Property(description: '排序值', type: 'integer', example: 100)]
    public int $sort = 100;
}
