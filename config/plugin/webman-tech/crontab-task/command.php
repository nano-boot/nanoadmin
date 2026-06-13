<?php

use plugin\nanoadmin\app\command\CrontabTaskExecCommand;
use plugin\nanoadmin\app\command\CrontabTaskListCommand;
use plugin\nanoadmin\app\command\MakeTaskCommand;

return [
    CrontabTaskListCommand::class,
    CrontabTaskExecCommand::class,
    MakeTaskCommand::class,
];
