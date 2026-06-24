<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\log\LogLoginQuery;
use plugin\nanoadmin\app\schema\log\LogLoginResponse;
use plugin\nanoadmin\app\service\LogLoginService;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\validator\log\LogLoginValidator;
use plugin\nanoadmin\app\common\R;
use support\annotation\Middleware;
use support\Request;
use support\Response;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;

/**
 * 登录日志控制器
 */
#[OA\Tag(name: '登录日志', description: '登录日志管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class LogLoginController extends BaseController
{
    private LogLoginService $logLoginService;
    private LogLoginValidator $validator;

    public function __construct(LogLoginService $logLoginService)
    {
        $this->logLoginService = $logLoginService;
        $this->validator = new LogLoginValidator();
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
        try {
            // ✅ 使用 validateData() 获取验证后的数据
            $params = $this->validator->validateData($request->get(), 'page');
            return R::paginate($this->getService()->getPage($params));
        } catch (\Exception $e) {
            return R::error($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/sys/login-log/{id}',
        summary: '登录日志详情',
        tags: ['登录日志']
    )]
    #[DataResponse(schema: LogLoginResponse::class)]
    public function show(int $id): Response
    {
        try {
            return parent::show($id);
        } catch (\Exception $e) {
            return R::error($e->getMessage());
        }
    }
}
