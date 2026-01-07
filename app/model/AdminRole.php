<?php

namespace plugin\theadmin\app\model;

/**
 * 管理员角色关联模型（中间表）
 * @property int $admin_id 管理员ID
 * @property int $role_id 角色ID
 */
class AdminRole extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_admin_role';

    /**
     * 主键
     * @var string|null
     */
    protected $primaryKey = null;

    /**
     * 是否自动维护时间戳
     * @var bool
     */
    public $timestamps = false;

    /**
     * 是否自增主键
     * @var bool
     */
    public $incrementing = false;

    /**
     * 可批量赋值的属性
     * @var array
     */
    protected $fillable = [
        'admin_id',
        'role_id'
    ];

    /**
     * 字段类型转换
     * @var array
     */
    protected $casts = [
        'admin_id' => 'integer',
        'role_id' => 'integer'
    ];
}
