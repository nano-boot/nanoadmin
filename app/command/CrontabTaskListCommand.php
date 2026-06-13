<?php

namespace plugin\nanoadmin\app\command;

use WebmanTech\CrontabTask\Commands\CrontabTaskListCommand as Origin;

class CrontabTaskListCommand extends Origin
{
    protected static string $defaultName = 'crontab-task:list';
    protected static string $defaultDescription = '展示 cron task 进程的定时任务名和执行时间';
}
