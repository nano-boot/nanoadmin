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

// 注册 OpenAPI / Swagger（完全基于 zircote/swagger-php，零依赖 webman-tech）
//   - /sys/openapi        渲染 swagger UI（用 swagger-ui CDN 引用）
//   - /sys/openapi/doc    输出 OpenAPI YAML（zircote Generator + 自定义 Processor + OpenApiModifier）
// 业务路由在上方 Route::group(...) 段手动注册，OpenAPI 注解仅用于生成文档。
// 注意：LogLoginController / 其他只写 #[OA\Get / OA\Post] 注解没在手写 Route::group 里的控制器，
//       需要用 OpenApiRouteRegister 扫描注册路由。
(new \plugin\nanoadmin\app\swagger\OpenApiRouteRegister())->register([
    base_path() . '/plugin/nanoadmin/app/controller',
]);

$openapiDocConfig = [
    'scan_path' => [
        base_path() . '/plugin/nanoadmin/app/controller',
        base_path() . '/plugin/nanoadmin/app/schema',
        base_path() . '/plugin/nanoadmin/app/swagger',
    ],
    'info' => [
        'title' => 'Nano Admin API',
        'version' => '1.0.0',
        'description' => 'Nano Admin 后台管理系统 API 文档',
    ],
    'servers' => [
        ['url' => '/', 'description' => '当前服务'],
    ],
    'auto_complete' => [
        'default_tag' => '其他',
    ],
];

$openapiDocController = new \plugin\nanoadmin\app\controller\OpenapiDocController();

// swagger UI 页面（用 swagger-ui CDN 自渲染，无需 webman-tech 的 view 模板）
Route::get('/sys/openapi', function () {
    return new \support\Response(
        200,
        ['Content-Type' => 'text/html; charset=utf-8'],
        <<<'HTML'
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>OpenAPI 文档</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css" />
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
  <script>
    window.onload = () => {
      window.ui = SwaggerUIBundle({
        url: window.location.pathname.replace(/\/?$/, '/') + 'doc',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis],
        layout: 'BaseLayout',
        docExpansion: 'none',
        defaultModelsExpandDepth: -1,
      });
    };
  </script>
</body>
</html>
HTML
    );
});

// openapi doc YAML
Route::get('/sys/openapi/doc', function () use ($openapiDocController, $openapiDocConfig) {
    return $openapiDocController->openapiDoc($openapiDocConfig);
});