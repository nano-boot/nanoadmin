<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\OperationLog;

/**
 * 操作日志服务类
 */
class OperationLogService extends BaseService
{
    public function __construct(OperationLog $model)
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
     * 记录操作日志
     * @param array $data 日志数据
     * @return OperationLog
     */
    public function recordOperation(array $data): OperationLog
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        return $this->model->create($data);
    }

    /**
     * 清理指定天数之前的日志
     * @param int $days 天数
     * @return int 删除数量
     */
    public function clearOldLogs(int $days = 90): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $this->model->where('created_at', '<', $cutoffDate)->delete();
    }
}
