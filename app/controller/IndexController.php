<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use plugin\theadmin\app\service\InstallService;
use support\Request;

class IndexController
{
    public function index()
    {
        return view('index/index', ['name' => 'theadmin']);
    }
}
