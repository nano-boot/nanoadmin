<?php

namespace plugin\nanoadmin\app\command;

use WebmanTech\CrontabTask\Commands\CrontabTaskExecCommand as Origin;

class CrontabTaskExecCommand extends Origin
{
    protected static string $defaultName = 'crontab-task:exec';
    protected static string $defaultDescription = '执行一个 task';
}
