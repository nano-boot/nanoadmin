<?php

namespace plugin\theadmin\app\model;

/**
 * 字典数据模型
 * @property int $id ID
 * @property int $dict_type_id 字典类型ID
 * @property string $label 字典标签
 * @property string $value 字典值
 * @property int $sort 排序
 * @property int $status 状态（1正常 0禁用）
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property int $deleted 删除标记
 */
class DictData extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_dict_data';

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
        'dict_type_id',
        'label',
        'value',
        'sort',
        'status'
    ];

    /**
     * 初始化搜索字段配置
     */
    protected static function boot(): void
    {
        parent::boot();

        static::setSearchLikeFields(['label', 'value']);
        static::setSearchEqualFields(['dict_type_id', 'status', 'deleted']);
        static::setSearchRangeFields(['created_at']);
    }

    /**
     * 关联字典类型
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dictType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DictType::class, 'dict_type_id', 'id');
    }
}
