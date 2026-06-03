<?php

use plugin\theadmin\app\command\CrontabTaskExecCommand;
use plugin\theadmin\app\command\CrontabTaskListCommand;
use plugin\theadmin\app\command\MakeTaskCommand;

return [
    CrontabTaskListCommand::class,
    CrontabTaskExecCommand::class,
    MakeTaskCommand::class,
];
