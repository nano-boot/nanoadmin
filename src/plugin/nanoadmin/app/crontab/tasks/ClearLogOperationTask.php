<?php

namespace plugin\nanoadmin\app\crontab\tasks;

use plugin\nanoadmin\app\model\LogOperation;
use plugin\nanoadmin\app\service\LogOperationService;
use WebmanTech\CrontabTask\BaseTask;

/**
 * 清理操作日志定时任务
 */
class ClearLogOperationTask extends BaseTask
{
    public function handle(): void
    {
        $model = new LogOperation();
        $service = new LogOperationService($model);
        $deleted = $service->clearOldLogs(90);

        echo sprintf(
            '[%s] 清理操作日志完成，删除 %d 条记录',
            date('Y-m-d H:i:s'),
            $deleted
        ) . PHP_EOL;
    }
}
