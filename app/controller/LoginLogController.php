<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\service\LoginLogService;
use support\Request;
use support\Response;

/**
 * 登录日志控制器
 */
class LoginLogController extends BaseController
{
    private LoginLogService $loginLogService;

    public function __construct(LoginLogService $loginLogService)
    {
        $this->loginLogService = $loginLogService;
    }

    protected function getService(): LoginLogService
    {
        return $this->loginLogService;
    }

    protected function getModelName(): string
    {
        return 'LoginLog';
    }
}
