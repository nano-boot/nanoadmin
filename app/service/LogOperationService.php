<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\LogOperation;

/**
 * 操作日志服务类
 */
class LogOperationService extends BaseService
{
    /**
     * 分页查询仅输出以下字段
     */
    protected static array $selectFields = [
        'id',
        'username',
        'module',
        'action',
        'request_method',
        'request_url',
        'response_code',
        'http_status',
        'cost_time',
        'ip',
        'created_at'
    ];

    public function __construct(LogOperation $model)
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
     * @return LogOperation
     */
    public function recordOperation(array $data): LogOperation
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
