<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use plugin\theadmin\app\service\InstallService;
use support\Request;

class IndexController
{
    protected InstallService $installService;

    public function __construct()
    {
        $this->installService = new InstallService();
    }

    public function index()
    {
        return view('index/index', ['name' => 'theadmin']);
    }

    /**
     * 安装向导入口页面
     */
    public function install()
    {
        // 检查是否已安装
        if ($this->installService->isInstalled()) {
            return view('index/already_installed');
        }

        return view('index/install');
    }

    /**
     * 检查安装状态
     */
    public function checkStatus(Request $request)
    {
        return R::success([
            'installed' => $this->installService->isInstalled(),
        ]);
    }
}
