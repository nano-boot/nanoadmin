<?php

namespace plugin\theadmin\app\model;

use think\model\Pivot;

class AdminRole extends Pivot
{
    /**
     * 表名
     * @var string
     */
    protected $name = 'sys_admin_role';

}