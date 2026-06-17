<?php

use Webman\Route;

// 认证相关路由
Route::group('/sys/auth', function () {
    // 登录接口（不需要认证）
    Route::post('/login', [plugin\nanoadmin\app\controller\AuthController::class, 'login']);

    // 需要认证的接口
    Route::post('/logout', [plugin\nanoadmin\app\controller\AuthController::class, 'logout']);
    Route::get('/info', [plugin\nanoadmin\app\controller\AuthController::class, 'info']);
    Route::get('/permissions', [plugin\nanoadmin\app\controller\AuthController::class, 'permissions']);
    Route::get('/menus', [plugin\nanoadmin\app\controller\AuthController::class, 'menus']);
    Route::post('/refresh', [plugin\nanoadmin\app\controller\AuthController::class, 'refresh']);
    Route::post('/check', [plugin\nanoadmin\app\controller\AuthController::class, 'check']);
});


// 管理员管理相关路由
Route::group('/sys/admin', function () {
    Route::get('', [plugin\nanoadmin\app\controller\AdminController::class, 'page']);
    Route::post('', [plugin\nanoadmin\app\controller\AdminController::class, 'create']);
    Route::put('/info', [plugin\nanoadmin\app\controller\AdminController::class, 'updateProfile']);
    Route::put('/password', [plugin\nanoadmin\app\controller\AdminController::class, 'updateCurrentPassword']);
    Route::get('/{id}', [plugin\nanoadmin\app\controller\AdminController::class, 'show']);
    Route::put('/{id}', [plugin\nanoadmin\app\controller\AdminController::class, 'update']);

    Route::delete('/batch', [plugin\nanoadmin\app\controller\AdminController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\nanoadmin\app\controller\AdminController::class, 'destroy']);

    Route::post('/{id}/roles', [plugin\nanoadmin\app\controller\AdminController::class, 'assignRoles']);
    Route::get('/{id}/roles', [plugin\nanoadmin\app\controller\AdminController::class, 'getRoles']);
});

// 角色管理相关路由
Route::group('/sys/role', function () {
    Route::get('', [plugin\nanoadmin\app\controller\RoleController::class, 'page']);
    Route::get('/select', [plugin\nanoadmin\app\controller\RoleController::class, 'selectList']);
    Route::post('', [plugin\nanoadmin\app\controller\RoleController::class, 'create']);
    Route::get('/{id}', [plugin\nanoadmin\app\controller\RoleController::class, 'show']);
    Route::put('/{id}', [plugin\nanoadmin\app\controller\RoleController::class, 'update']);
    Route::delete('/batch', [plugin\nanoadmin\app\controller\RoleController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\nanoadmin\app\controller\RoleController::class, 'destroy']);
    Route::post('/{id}/permissions', [plugin\nanoadmin\app\controller\RoleController::class, 'assignPermissions']);
    Route::get('/{id}/permissions', [plugin\nanoadmin\app\controller\RoleController::class, 'getPermissions']);
    Route::post('/{id}/menus', [plugin\nanoadmin\app\controller\RoleController::class, 'assignMenus']);
    Route::get('/{id}/menus', [plugin\nanoadmin\app\controller\RoleController::class, 'getMenus']);

});

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

