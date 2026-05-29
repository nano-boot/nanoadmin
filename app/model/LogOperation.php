<?php

namespace plugin\theadmin\app\model;

use Illuminate\Database\Eloquent\Builder;

/**
 * 操作日志模型
 * @property int $id 日志ID
 * @property int $admin_id 管理员ID
 * @property string $username 管理员名称
 * @property string $module 操作模块
 * @property string $action 操作类型
 * @property string $description 操作描述
 * @property string $request_method 请求方法
 * @property string $request_url 请求URL
 * @property string $request_params 请求参数
 * @property int $response_code 响应状态码
 * @property float $cost_time 消耗时间（秒）
 * @property string $ip 操作IP
 * @property string $created_at 操作时间
 */
class LogOperation extends BaseModel
{
    protected $table = 'sys_log_operation';
    protected $primaryKey = 'id';

    protected $fillable = [
        'admin_id',
        'username',
        'module',
        'action',
        'description',
        'request_method',
        'request_url',
        'request_params',
        'response_code',
        'cost_time',
        'ip',
        'created_at'
    ];

    protected $casts = [
        'admin_id' => 'integer',
        'response_code' => 'integer',
        'cost_time' => 'float',
        'created_at' => 'datetime',
    ];

    protected static array $searchLikeFields = ['username', 'module', 'action', 'description', 'ip'];
    protected static array $searchEqualFields = ['admin_id', 'module', 'action', 'request_method', 'response_code'];
    protected static array $searchKeywordFields = ['username', 'description', 'request_url'];
    protected static array $searchRangeFields = ['created_at'];

    public function handleSearch(Builder $query, array $params): Builder
    {
        $query = parent::handleSearch($query, $params);

        return $query;
    }
}
