<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\attribute\Permission;
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
 * Phase 2 注解化（来源：authorization-refactoring-plan.md §1）：
 *  - 类级 #[Permission] 提供兜底权限码 sys:log
 *  - 方法级 #[Permission] 精确声明每个方法的权限码（与 route_permissions 对齐）
 *  - route_permissions 中操作日志只读，全部映射到 sys:log:query（与登录日志共用权限族）
 */
#[OA\Tag(name: '操作日志', description: '系统操作日志管理')]
#[Permission(title: '操作日志', code: 'sys:log')]
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
    #[Permission(title: '操作日志列表', code: 'sys:log:query', action: 'query')]
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
    #[Permission(title: '操作日志详情', code: 'sys:log:query', action: 'query')]
    #[DataResponse(schema: LogOperationResponse::class)]
    public function show(int $id): Response
    {
        $params = $this->validator->scene('show')->setPath()->check();
        return R::success($this->service->getById($params['id']), '获取详情成功');
    }
}
