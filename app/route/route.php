<?php

declare(strict_types=1);

use Webman\Route;
use plugin\nanoadmin\app\controller\InstallController;

/*
 * NanoAdmin 插件路由
 *
 * 由 config/route.php 通过 require_once 加载。
 * InstallGuard 已对 /install 系列路径放行，无需额外鉴权。
 */

// 安装向导（独立子应用，不进入插件鉴权中间件栈）
Route::group('/install', function () {
    Route::get('',           [InstallController::class, 'index']);
    Route::post('/check-env', [InstallController::class, 'checkEnv']);
    Route::post('/test-db',   [InstallController::class, 'testDatabase']);
    Route::post('/run',       [InstallController::class, 'run']);
});