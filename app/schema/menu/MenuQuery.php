<?php

namespace plugin\nanoadmin\app\schema\menu;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\library\swagger\QuerySchema;

/**
 * 菜单树查询参数
 *
 * 只做 OpenAPI 文档，校验统一走 MenuValidator（ValidatorBase）。
 *
 * @see plugin\nanoadmin\app\validator\menu\MenuValidator
 */
#[OA\Schema(title: '菜单树查询', description: '菜单树查询参数')]
class MenuQuery extends QuerySchema
{
    #[OA\Property(description: '父菜单ID（0 表示顶级菜单）', type: 'integer', example: 0)]
    public int $parent_id = 0;

    #[OA\Property(description: '关键词（菜单名称模糊搜索）', type: 'string', example: '系统')]
    public string $keyword = '';

    #[OA\Property(description: '菜单名称', type: 'string', example: '用户管理')]
    public string $name = '';

    #[OA\Property(description: '菜单状态（true启用 false禁用）', type: 'boolean', example: true)]
    public bool $status = true;

    #[OA\Property(description: '菜单类型：D=目录 M=菜单 B=按钮 L=外链 I=内嵌', type: 'string', example: 'M')]
    public string $type = '';

    #[OA\Property(description: '是否隐藏（true隐藏 false显示）', type: 'boolean', example: false)]
    public bool $hidden = false;

    #[OA\Property(description: '是否只获取启用的菜单', type: 'boolean', example: true)]
    public bool $only_enabled = true;
}
