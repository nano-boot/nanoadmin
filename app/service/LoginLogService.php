<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\LoginLog;

/**
 * 登录日志服务类
 */
class LoginLogService extends BaseService
{
    public function __construct(LoginLog $model)
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
     * @return LoginLog
     */
    public function recordLogin(array $data): LoginLog
    {
        $data['login_time'] = $data['login_time'] ?? date('Y-m-d H:i:s');
        return $this->model->create($data);
    }
}
