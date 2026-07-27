<?php

namespace plugin\nanoadmin\app\model\tool;

use plugin\nanoadmin\app\model\BaseModel;

/**
 * 代码生成配置模型
 * @property int $id ID
 * @property string $table_name 表名称
 * @property string $table_comment 表描述
 * @property string $class_name 类名称
 * @property string $business_name 业务名称
 * @property string $namespace 命名空间
 * @property string $package_name 包名称
 * @property string $template 应用类型：plugin/app
 * @property string $tpl_category 生成类型：single(单表)/tree(树表)
 * @property string $menu_name 菜单名称
 * @property int $belong_menu_id 所属菜单ID
 * @property string $generate_menus 生成的菜单
 * @property array|null $options 扩展选项
 * @property string $source 数据源
 * @property int $deleted_at 删除时间（0未删除，时间戳为已删除）
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 */
class GenerateTables extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_generate_tables';

    /**
     * 主键
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * 可批量赋值的属性
     * @var array
     */
    protected $fillable = [
        'table_name',
        'table_comment',
        'class_name',
        'business_name',
        'namespace',
        'package_name',
        'template',
        'tpl_category',
        'menu_name',
        'belong_menu_id',
        'generate_menus',
        'options',
        'source',
    ];

    /**
     * 需要转换的属性类型
     * @var array
     */
    protected $casts = [
        'status' => 'integer',
        'sort' => 'integer',
        'deleted_at' => 'integer',
        'belong_menu_id' => 'integer',
        'options' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 搜索字段配置
     * @var array
     */
    protected static array $searchLikeFields = ['table_name', 'table_comment', 'class_name', 'business_name'];
    protected static array $searchEqualFields = ['namespace', 'package_name', 'template', 'tpl_category'];
    protected static array $searchKeywordFields = ['table_name', 'table_comment'];
    protected static array $searchRangeFields = ['created_at'];

    /**
     * 关联字段
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function columns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GenerateColumns::class, 'table_id', 'id');
    }
}
