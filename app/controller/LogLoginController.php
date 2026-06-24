<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\log\LogLoginQuery;
use plugin\nanoadmin\app\schema\log\LogLoginResponse;
use plugin\nanoadmin\app\service\LogLoginService;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\validator\log\LogLoginValidator;
use plugin\nanoadmin\app\common\R;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 登录日志控制器
 *
 */
#[OA\Tag(name: '登录日志', description: '登录日志管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class LogLoginController extends BaseController
{
    private LogLoginService $service;
    private LogLoginValidator $validator;

    public function __construct(LogLoginService $service, LogLoginValidator $validator)
    {
        $this->service = $service;
        $this->validator = $validator;
    }

    #[OA\Get(
        path: '/sys/login-log',
        summary: '登录日志列表',
        tags: ['登录日志'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => LogLoginQuery::class]
    )]
    #[PageResponse(schema: LogLoginResponse::class)]
    public function page(Request $request): Response
    {
        $params = $this->validator->scene('page')->setGet()->check();
        return R::paginate($this->service->getPage($params));
    }

    #[OA\Get(
        path: '/sys/login-log/{id}',
        summary: '登录日志详情',
        tags: ['登录日志'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '日志ID'],
        ]]
    )]
    #[DataResponse(schema: LogLoginResponse::class)]
    public function show(int $id): Response
    {
        $params = $this->validator->scene('show')->setPath()->check();
        return R::success($this->service->getById($params['id']), '获取详情成功');
    }
}
