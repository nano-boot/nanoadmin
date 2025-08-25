<?php

use Webman\Route;

// 认证相关路由
Route::group('/sys/auth', function () {
    // 登录接口（不需要认证）
    Route::post('/login', [plugin\theadmin\app\controller\AuthController::class, 'login']);
    
    // 需要认证的接口
    Route::post('/logout', [plugin\theadmin\app\controller\AuthController::class, 'logout']);
    Route::get('/info', [plugin\theadmin\app\controller\AuthController::class, 'info']);
    Route::get('/permissions', [plugin\theadmin\app\controller\AuthController::class, 'permissions']);
    Route::get('/menus', [plugin\theadmin\app\controller\AuthController::class, 'menus']);
    Route::post('/refresh', [plugin\theadmin\app\controller\AuthController::class, 'refresh']);
    Route::post('/check', [plugin\theadmin\app\controller\AuthController::class, 'check']);
});


// 管理员管理相关路由（需要认证）
Route::group('/sys/admin', function () {
    Route::get('', [plugin\theadmin\app\controller\AdminController::class, 'index']);
    Route::post('', [plugin\theadmin\app\controller\AdminController::class, 'store']);
    Route::get('/{id}', [plugin\theadmin\app\controller\AdminController::class, 'show']);
    Route::put('/{id}', [plugin\theadmin\app\controller\AdminController::class, 'update']);

    // 将批量删除路由放在单个删除路由之前
    Route::delete('/batch', [plugin\theadmin\app\controller\AdminController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\theadmin\app\controller\AdminController::class, 'destroy']);

    Route::post('/{id}/roles', [plugin\theadmin\app\controller\AdminController::class, 'assignRoles']);
    Route::get('/{id}/roles', [plugin\theadmin\app\controller\AdminController::class, 'getRoles']);
});

// 角色管理相关路由（需要认证）
Route::group('/sys/role', function () {
    Route::get('', [plugin\theadmin\app\controller\RoleController::class, 'index']);
    Route::post('', [plugin\theadmin\app\controller\RoleController::class, 'store']);
    Route::get('/{id}', [plugin\theadmin\app\controller\RoleController::class, 'show']);
    Route::put('/{id}', [plugin\theadmin\app\controller\RoleController::class, 'update']);
    Route::delete('/batch', [plugin\theadmin\app\controller\RoleController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\theadmin\app\controller\RoleController::class, 'destroy']);
    Route::post('/{id}/permissions', [plugin\theadmin\app\controller\RoleController::class, 'assignPermissions']);
    Route::get('/{id}/permissions', [plugin\theadmin\app\controller\RoleController::class, 'getPermissions']);
    Route::post('/{id}/menus', [plugin\theadmin\app\controller\RoleController::class, 'assignMenus']);
    Route::get('/{id}/menus', [plugin\theadmin\app\controller\RoleController::class, 'getMenus']);

});

// 权限管理相关路由（需要认证）
Route::group('/sys/permissions', function () {
    Route::get('', [plugin\theadmin\app\controller\PermissionController::class, 'index']);
    Route::post('', [plugin\theadmin\app\controller\PermissionController::class, 'store']);
    Route::get('/{id}', [plugin\theadmin\app\controller\PermissionController::class, 'show']);
    Route::put('/{id}', [plugin\theadmin\app\controller\PermissionController::class, 'update']);
    Route::delete('/batch', [plugin\theadmin\app\controller\PermissionController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\theadmin\app\controller\PermissionController::class, 'destroy']);

});

// 菜单管理相关路由（需要认证）
Route::group('/sys/menus', function () {
    Route::get('', [plugin\theadmin\app\controller\MenuController::class, 'index']);
    Route::post('', [plugin\theadmin\app\controller\MenuController::class, 'store']);
    Route::post('/tree', [plugin\theadmin\app\controller\MenuController::class, 'tree']);
    Route::get('/routes', [plugin\theadmin\app\controller\MenuController::class, 'routes']);
    Route::get('/{id}', [plugin\theadmin\app\controller\MenuController::class, 'show']);
    Route::put('/{id}', [plugin\theadmin\app\controller\MenuController::class, 'update']);
//    Route::delete('/batch', [plugin\theadmin\app\controller\MenuController::class, 'batchDestroy']);
    Route::delete('/{id}', [plugin\theadmin\app\controller\MenuController::class, 'destroy']);
    Route::post('/sort', [plugin\theadmin\app\controller\MenuController::class, 'sort']);

});