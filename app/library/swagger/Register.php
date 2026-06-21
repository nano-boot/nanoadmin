<?php

// 注册 OpenAPI / Swagger 基于 zircote/swagger-php
//   - /sys/openapi        渲染 swagger UI
//   - /sys/openapi/doc    输出 OpenAPI YAML（zircote Generator + 自定义 Processor + OpenApiModifier）
// 业务路由在上方 Route::group(...) 段手动注册，OpenAPI 注解仅用于生成文档。
// 注意：LogLoginController / 其他只写 #[OA\Get / OA\Post] 注解没在手写 Route::group 里的控制器，
//       需要用 OpenApiBootstrap 扫描注册路由。
\plugin\nanoadmin\app\library\swagger\OpenApiBootstrap::register([
    base_path() . '/plugin/nanoadmin/app/controller',
]);