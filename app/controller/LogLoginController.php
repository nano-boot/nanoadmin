<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\query\LogLoginQuery;
use plugin\nanoadmin\app\schema\response\LogLoginResponse;
use plugin\nanoadmin\app\service\LogLoginService;
use plugin\nanoadmin\app\swagger\OpenApiModifier;
use plugin\nanoadmin\app\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\swagger\annotation\response\SimpleResponse;
use plugin\nanoadmin\app\validator\LogLoginValidator;
use support\annotation\Middleware;
use support\Request;
use support\Response;
use WebmanTech\Swagger\DTO\SchemaConstants;

/**
 * 登录日志控制器
 *
 * 使用 AbstractResourceController 抽象：
 *  - schema 类通过 $querySchema / $responseSchema 声明
 *  - validateQuery($request) 自动完成参数校验
 *  - CRUD 方法直接继承 BaseController
 *
 * RequestBody 自动注入（通过 modify 回调）：
 *  - create 方法声明 x: [OpenApiModifier::X_REQUEST_BODY => LogLoginResponse::class]
 *  - modify 回调自动追加 OA\RequestBody(application/json + ref)
 */
#[OA\Tag(name: '登录日志', description: '登录日志管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class LogLoginController extends AbstractResourceController
{
    private LogLoginService $logLoginService;

    public function __construct(LogLoginService $logLoginService)
    {
        $this->logLoginService = $logLoginService;
        $this->queryValidator = LogLoginValidator::class;
        $this->resourceTag    = '登录日志';
    }

    protected function getService(): LogLoginService
    {
        return $this->logLoginService;
    }

    protected function getModelName(): string
    {
        return 'LogLogin';
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
        $this->validateQuery($request);
        return parent::page($request);
    }

    #[OA\Get(
        path: '/sys/login-log/{id}',
        summary: '登录日志详情',
        tags: ['登录日志']
    )]
    #[SimpleResponse(schema: LogLoginResponse::class)]
    public function show(int $id = 0): Response
    {
        return parent::show($id);
    }

    #[OA\Post(
        path: '/sys/login-log',
        summary: '创建登录日志',
        tags: ['登录日志'],
        x: [OpenApiModifier::X_REQUEST_BODY => LogLoginResponse::class]
    )]
    #[SimpleResponse()]
    public function create(Request $request): Response
    {
        return parent::create($request);
    }
}
