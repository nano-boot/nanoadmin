<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\LogLogin;

/**
 * 登录日志服务类
 */
class LogLoginService extends BaseService
{
    /**
     * 分页查询仅输出以下字段
     */
    protected static array $selectFields = [
        'id',
        'username',
        'ip',
        'location',
        'status',
        'login_info',
        'login_time'
    ];

    public function __construct(LogLogin $model)
    {
        parent::__construct($model);
    }

    protected function getNotFoundCode(): Code
    {
        return Code::LOG_NOT_FOUND;
    }

    protected function getNotFoundMessage(): string
    {
        return '日志不存在';
    }

    /**
     * 记录登录日志
     * @param array $data 日志数据
     * @return LogLogin
     */
    public function recordLogin(array $data): LogLogin
    {
        $data['login_time'] = $data['login_time'] ?? date('Y-m-d H:i:s');
        return $this->model->create($data);
    }
}
