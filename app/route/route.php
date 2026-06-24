<?php

use Webman\Route;

// 权限管理相关路由
Route::group('/sys/permissions', function () {
    Route::get('', [plugin\nanoadmin\app\controller\PermissionController::class, 'page']);
    Route::post('', [plugin\nanoadmin\app\controller\PermissionController::class, 'store']);
    Route::get('/{id}', [plugin\nanoadmin\app\controller\PermissionController::class, 'show']);
    Route::put('/{id}', [plugin\nanoadmin\app\controller\PermissionController::class, 'update']);
    Route::delete('/batch', [plugin\nanoadmin\app\controller\PermissionController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\nanoadmin\app\controller\PermissionController::class, 'destroy']);

});

// 菜单管理相关路由
Route::group('/sys/menu', function () {
    Route::get('', [plugin\nanoadmin\app\controller\MenuController::class, 'tree']);
    Route::post('', [plugin\nanoadmin\app\controller\MenuController::class, 'store']);
    Route::get('/route', [plugin\nanoadmin\app\controller\MenuController::class, 'route']);
    Route::get('/{id}', [plugin\nanoadmin\app\controller\MenuController::class, 'show']);
    Route::put('/{id}', [plugin\nanoadmin\app\controller\MenuController::class, 'update']);
//    Route::delete('/batch', [plugin\nanoadmin\app\controller\MenuController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\nanoadmin\app\controller\MenuController::class, 'destroy']);
    Route::post('/sort', [plugin\nanoadmin\app\controller\MenuController::class, 'sort']);

});
