<?php

use WebmanTech\CrontabTask\Schedule;

return (new Schedule())
    ->addTask(
        'clear_log_operation',
        '0 2 * * *',
        \plugin\nanoadmin\app\crontab\tasks\ClearLogOperationTask::class
    )
    ->buildProcesses();
