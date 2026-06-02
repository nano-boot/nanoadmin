<?php

namespace plugin\theadmin\app\model;

use Illuminate\Database\Eloquent\Builder;

/**
 * 登录日志模型
 * @property int $id 日志ID
 * @property int $admin_id 管理员ID
 * @property string $username 用户名
 * @property string $ip 登录IP
 * @property string $user_agent User-Agent
 * @property string $location 登录地点
 * @property int $status 登录状态（0失败 1成功）
 * @property string $login_info 登录信息（成功：登录成功 / 失败：失败原因）
 * @property string $login_time 登录时间
 */
class LogLogin extends BaseModel
{
    protected $table = 'sys_log_login';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'username',
        'ip',
        'user_agent',
        'location',
        'status',
        'login_info',
        'login_time',
    ];

    protected $casts = [
        'admin_id' => 'integer',
        'status' => 'integer',
        'login_time' => 'datetime',
    ];

    protected static array $searchLikeFields = ['username', 'ip', 'location'];
    protected static array $searchEqualFields = ['status', 'admin_id'];
    protected static array $searchKeywordFields = ['username', 'ip'];
    protected static array $searchRangeFields = ['login_time'];

    public function handleSearch(Builder $query, array $params): Builder
    {
        $query = parent::handleSearch($query, $params);

        return $query;
    }
}
