<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\service\LogOperationService;
use support\Request;
use support\Response;

/**
 * 操作日志控制器
 */
class LogOperationController extends BaseController
{
    private LogOperationService $logOperationService;

    public function __construct(LogOperationService $logOperationService)
    {
        $this->logOperationService = $logOperationService;
    }

    protected function getService(): LogOperationService
    {
        return $this->logOperationService;
    }

    protected function getModelName(): string
    {
        return 'LogOperation';
    }
}
