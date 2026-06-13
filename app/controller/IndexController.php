<?php

namespace plugin\nanoadmin\app\controller;

use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\service\InstallService;
use support\Request;

class IndexController
{
    public function index()
    {
        return view('index/index', ['name' => 'nanoadmin']);
    }
}
