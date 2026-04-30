<?php

namespace plugin\theadmin\app\model;

use Illuminate\Database\Eloquent\Builder;

/**
 * 字典类型模型
 * @property int $id ID
 * @property string $name 字典名称
 * @property string $code 字典编码
 * @property string $description 字典描述
 * @property int $status 状态（1正常 0禁用）
 * @property int $sort 排序
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property int $deleted 删除标记
 */
class DictType extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_dict_type';

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
        'code',
        'description',
        'status',
        'sort'
    ];

    /**
     * 初始化搜索字段配置
     */
    protected static function boot(): void
    {
        parent::boot();

        static::setSearchLikeFields(['name', 'code', 'description']);
        static::setSearchEqualFields(['status', 'deleted']);
        static::setSearchRangeFields(['created_at']);
    }

    /**
     * 关联字典数据
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function dictData(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DictData::class, 'dict_type_id', 'id');
    }
}
