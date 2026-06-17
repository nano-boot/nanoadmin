<?php

use Webman\Route;

// 认证相关路由
// AuthController 已通过类级/方法级 #[OA\Post]、#[OA\Get] 注解注册，
// 由 OpenApiRouteRegister 自动扫描注册（详见 plugin/nanoadmin/app/library/swagger/OpenApiRouteRegister）。
// 中间件：login 方法用 #[Middleware()] 覆盖为无中间件；其余方法走类级 AuthMiddleware。
// 不再在此手写 Route::group('/sys/auth', ...)。


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

// 字典类型管理相关路由
Route::group('/sys/dict-type', function () {
    Route::get('', [plugin\nanoadmin\app\controller\DictTypeController::class, 'page']);
    Route::post('', [plugin\nanoadmin\app\controller\DictTypeController::class, 'create']);
    Route::get('/{id}', [plugin\nanoadmin\app\controller\DictTypeController::class, 'show']);
    Route::put('/{id}', [plugin\nanoadmin\app\controller\DictTypeController::class, 'update']);
    Route::delete('/batch', [plugin\nanoadmin\app\controller\DictTypeController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\nanoadmin\app\controller\DictTypeController::class, 'destroy']);
});

// 字典数据管理相关路由
Route::group('/sys/dict-data', function () {
    Route::get('', [plugin\nanoadmin\app\controller\DictDataController::class, 'page']);
    Route::post('', [plugin\nanoadmin\app\controller\DictDataController::class, 'create']);
    Route::get('/{id}', [plugin\nanoadmin\app\controller\DictDataController::class, 'show']);
    Route::put('/{id}', [plugin\nanoadmin\app\controller\DictDataController::class, 'update']);
    Route::delete('/batch', [plugin\nanoadmin\app\controller\DictDataController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\nanoadmin\app\controller\DictDataController::class, 'destroy']);
});

// 文件管理相关路由
Route::group('/sys/files', function () {
    Route::get('', [plugin\nanoadmin\app\controller\FileController::class, 'page']);
    Route::post('', [plugin\nanoadmin\app\controller\FileController::class, 'upload']);
    Route::post('/batch', [plugin\nanoadmin\app\controller\FileController::class, 'batchUpload']);
    Route::get('/stats', [plugin\nanoadmin\app\controller\FileController::class, 'stats']);
    Route::get('/{id}', [plugin\nanoadmin\app\controller\FileController::class, 'show']);
    Route::put('/{id}', [plugin\nanoadmin\app\controller\FileController::class, 'update']);
    Route::get('/{id}/download', [plugin\nanoadmin\app\controller\FileController::class, 'download']);
    Route::delete('/batch', [plugin\nanoadmin\app\controller\FileController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\nanoadmin\app\controller\FileController::class, 'destroy']);
});

// 配置管理相关路由
Route::group('/sys/config', function () {
    Route::get('', [plugin\nanoadmin\app\controller\ConfigController::class, 'page']);
    Route::get('/group', [plugin\nanoadmin\app\controller\ConfigController::class, 'getByGroup']);
    Route::post('', [plugin\nanoadmin\app\controller\ConfigController::class, 'create']);
    Route::put('/batch', [plugin\nanoadmin\app\controller\ConfigController::class, 'batchUpdate']);
    Route::get('/{id}', [plugin\nanoadmin\app\controller\ConfigController::class, 'show']);
    Route::put('/{id}', [plugin\nanoadmin\app\controller\ConfigController::class, 'update']);
    Route::delete('/batch', [plugin\nanoadmin\app\controller\ConfigController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\nanoadmin\app\controller\ConfigController::class, 'destroy']);
});

// 操作日志相关路由
Route::group('/sys/operation-log', function () {
    Route::get('', [plugin\nanoadmin\app\controller\LogOperationController::class, 'page']);
    Route::get('/{id}', [plugin\nanoadmin\app\controller\LogOperationController::class, 'show']);
});

