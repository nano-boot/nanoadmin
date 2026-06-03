<?php

namespace plugin\theadmin\app\command;

use WebmanTech\CrontabTask\Commands\MakeTaskCommand as Origin;

class MakeTaskCommand extends Origin
{
    protected static string $defaultName = 'make:crontab-task';
    protected static string $defaultDescription = '创建 crontab task';
}
