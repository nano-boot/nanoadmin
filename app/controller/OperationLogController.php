<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\service\OperationLogService;
use support\Request;
use support\Response;

/**
 * 操作日志控制器
 */
class OperationLogController extends BaseController
{
    private OperationLogService $operationLogService;

    public function __construct(OperationLogService $operationLogService)
    {
        $this->operationLogService = $operationLogService;
    }

    protected function getService(): OperationLogService
    {
        return $this->operationLogService;
    }

    protected function getModelName(): string
    {
        return 'OperationLog';
    }
}
