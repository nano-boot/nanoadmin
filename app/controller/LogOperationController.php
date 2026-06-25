<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\log\LogOperationQuery;
use plugin\nanoadmin\app\schema\log\LogOperationResponse;
use plugin\nanoadmin\app\service\LogOperationService;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\validator\log\LogOperationValidator;
use plugin\nanoadmin\app\common\R;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 操作日志控制器
 *
 */
#[OA\Tag(name: '操作日志', description: '系统操作日志管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class LogOperationController extends BaseController
{
    private LogOperationService $service;
    private LogOperationValidator $validator;

    public function __construct(LogOperationService $service, LogOperationValidator $validator)
    {
        $this->service = $service;
        $this->validator = $validator;
    }

    #[OA\Get(
        path: '/sys/operation-log',
        summary: '操作日志列表',
        tags: ['操作日志'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => LogOperationQuery::class]
    )]
    #[PageResponse(schema: LogOperationResponse::class)]
    public function page(Request $request): Response
    {
        $params = $this->validator->scene('page')->setGet()->check();
        return R::paginate($this->service->getPage($params));
    }

    #[OA\Get(
        path: '/sys/operation-log/{id}',
        summary: '操作日志详情',
        tags: ['操作日志'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '日志ID'],
        ]]
    )]
    #[DataResponse(schema: LogOperationResponse::class)]
    public function show(int $id): Response
    {
        $params = $this->validator->scene('show')->setPath()->check();
        return R::success($this->service->getById($params['id']), '获取详情成功');
    }
}
