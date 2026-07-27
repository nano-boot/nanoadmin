<?php

namespace plugin\nanoadmin\app\model\tool;

use plugin\nanoadmin\app\model\BaseModel;

/**
 * 代码生成字段模型
 * @property int $id ID
 * @property int $table_id 所属表ID
 * @property string $column_name 字段名称
 * @property string $column_comment 字段描述
 * @property string $column_type 字段类型
 * @property string $php_type PHP类型
 * @property int $primary_key 主键：1-否 2-是
 * @property int $required 必填：1-否 2-是
 * @property int $insertable 可新增：1-否 2-是
 * @property int $editable 可编辑：1-否 2-是
 * @property int $show_list 列表显示：1-否 2-是
 * @property int $queriable 可查询：1-否 2-是
 * @property string $query_type 查询方式
 * @property string $view_type 视图类型
 * @property string $dict_type 字典类型
 * @property array|null $options 扩展选项
 * @property int $sort 排序
 * @property int $override 覆盖：0-否 1-是
 * @property string $default_value 默认值
 * @property int $deleted_at 删除时间（0未删除，时间戳为已删除）
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 */
class GenerateColumns extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_generate_columns';

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
        'table_id',
        'column_name',
        'column_comment',
        'column_type',
        'php_type',
        'primary_key',
        'required',
        'insertable',
        'editable',
        'show_list',
        'queriable',
        'query_type',
        'view_type',
        'dict_type',
        'options',
        'sort',
        'override',
        'default_value',
    ];

    /**
     * 需要转换的属性类型
     * @var array
     */
    protected $casts = [
        'table_id' => 'integer',
        'primary_key' => 'integer',
        'required' => 'integer',
        'insertable' => 'integer',
        'editable' => 'integer',
        'show_list' => 'integer',
        'queriable' => 'integer',
        'sort' => 'integer',
        'override' => 'integer',
        'deleted_at' => 'integer',
        'options' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 搜索字段配置
     * @var array
     */
    protected static array $searchLikeFields = ['column_name', 'column_comment'];
    protected static array $searchEqualFields = ['table_id', 'primary_key', 'required'];
    protected static array $searchKeywordFields = ['column_name', 'column_comment'];
    protected static array $searchRangeFields = [];

    /**
     * 关联表
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function table(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(GenerateTables::class, 'table_id', 'id');
    }
}
