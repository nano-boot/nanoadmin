<?php

namespace plugin\nanoadmin\app\swagger;

use OpenApi\Attributes as OA;

/**
 * OpenAPI Info 占位类
 *
 * swagger-php 在 validate() 阶段会校验 OpenApi 必须有 Info 字段；
 * 这里提供占位的 #[OA\Info]，让扫描阶段就把 Info 挂上去。
 *
 * 真正的 title/version/description 由 plugin/nanoadmin/app/route/route.php
 * 里 OpenApiModifier::process() 运行时覆盖。
 */
#[OA\Info(title: 'Nano Admin API', version: '1.0.0', description: 'Nano Admin 后台管理系统 API 文档')]
class OpenApiInfo
{
}
