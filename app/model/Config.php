<?php

namespace plugin\theadmin\app\model;

use Illuminate\Database\Eloquent\Builder;

/**
 * 系统配置模型
 * @property int $id 配置ID
 * @property string $name 配置名称
 * @property string $key 配置键名
 * @property string $value 配置值
 * @property string $type 配置类型（text/number/boolean/select/radio/checkbox/textarea/json）
 * @property string $options 选项配置（JSON格式）
 * @property string $group 配置分组
 * @property string $description 配置描述
 * @property int $sort 排序
 * @property int $status 状态（1正常 0禁用）
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property int $deleted 删除标记
 */
class Config extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_config';

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
        'name',
        'key',
        'value',
        'type',
        'options',
        'group',
        'description',
        'sort',
        'status'
    ];

    /**
     * 搜索字段配置（显式声明，避免静态属性继承污染）
     * @var array
     */
    protected static array $searchLikeFields = ['name', 'key', 'description'];
    protected static array $searchEqualFields = ['group', 'type', 'status', 'deleted'];
    protected static array $searchKeywordFields = ['name', 'key'];
    protected static array $searchRangeFields = ['created_at'];

    /**
     * 初始化搜索字段配置
     */
    protected static function boot(): void
    {
        parent::boot();
    }

    /**
     * 自定义搜索逻辑
     * @param Builder $query
     * @param array $params
     * @return Builder
     */
    public function handleSearch(Builder $query, array $params): Builder
    {
        $query = parent::handleSearch($query, $params);

        return $query;
    }
}
