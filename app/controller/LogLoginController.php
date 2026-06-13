<?php

namespace plugin\nanoadmin\app\controller;

use plugin\nanoadmin\app\service\LogLoginService;
use support\Request;
use support\Response;

/**
 * 登录日志控制器
 */
class LogLoginController extends BaseController
{
    private LogLoginService $logLoginService;

    public function __construct(LogLoginService $logLoginService)
    {
        $this->logLoginService = $logLoginService;
    }

    protected function getService(): LogLoginService
    {
        return $this->logLoginService;
    }

    protected function getModelName(): string
    {
        return 'LogLogin';
    }
}
