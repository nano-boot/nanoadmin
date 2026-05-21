<?php

use Webman\Route;

Route::get('/', function () {
    return 'Hello World';
});

// 安装向导页面路由 - 返回静态HTML文件
Route::get('/install', function () {
    return response()->file(base_path() . '/public/install.html');
});
Route::get('/install.html', function () {
    return response()->file(base_path() . '/public/install.html');
});

require_once __DIR__ . '/../app/route/route.php';
