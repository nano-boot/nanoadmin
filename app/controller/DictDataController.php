<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\attribute\Permission;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\dict\DictDataQuery;
use plugin\nanoadmin\app\schema\dict\DictDataRequest;
use plugin\nanoadmin\app\schema\dict\DictDataResponse;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\Annotation\Response\PageResponse;
use plugin\nanoadmin\app\library\swagger\Annotation\Response\DataResponse;
use plugin\nanoadmin\app\validator\dict\DictDataValidator;
use plugin\nanoadmin\app\service\DictDataService;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 字典数据控制器
 *
 * 采用「薄 Controller」模式：
 * - Controller 只负责接收请求、调用验证器、调用 Service、返回响应
 * - 异常由全局异常处理器统一处理
 * - 业务逻辑全部在 Service 层
 *
 * 验证方式：使用链式调用 $validator->scene()->setXxx()->check()
 *
 * Phase 2 注解化（来源：authorization-refactoring-plan.md §1）：
 *  - 类级 #[Permission] 提供兜底权限码 sys:dict:type
 *  - 方法级 #[Permission] 精确声明每个方法的权限码（与 route_permissions 对齐）
 *  - 注意：route_permissions 中 dict-data 接口统一映射到 sys:dict:type:* 权限族
 *    （与 DictTypeController 共用一套权限），迁移时保持。
 */
#[OA\Tag(name: '字典数据', description: '字典数据管理')]
#[Permission(title: '字典数据管理', code: 'sys:dict:type', module: 'system')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class DictDataController extends BaseController
{
    private DictDataService $dictDataService;
    private DictDataValidator $validator;

    public function __construct(DictDataService $dictDataService, DictDataValidator $validator)
    {
        $this->dictDataService = $dictDataService;
        $this->validator = $validator;
    }

    #[OA\Get(
        path: '/sys/dict-data',
        summary: '字典数据列表',
        tags: ['字典数据'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => DictDataQuery::class]
    )]
    #[Permission(title: '字典数据列表', code: 'sys:dict:type:page', module: 'system', action: 'page')]
    #[PageResponse(schema: DictDataResponse::class)]
    public function page(Request $request): Response
    {
        $params = $this->validator->scene('page')->setGet()->check();
        return R::paginate($this->dictDataService->getPage($params));
    }

    #[OA\Get(
        path: '/sys/dict-data/{id}',
        summary: '字典数据详情',
        tags: ['字典数据'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '字典数据ID'],
        ]]
    )]
    #[Permission(title: '字典数据详情', code: 'sys:dict:type:page', module: 'system', action: 'page')]
    #[DataResponse(schema: DictDataResponse::class)]
    public function show(int $id): Response
    {
        $params = $this->validator->scene('show')->setPath()->check();
        return R::success($this->dictDataService->getById($params['id']), '获取详情成功');
    }

    #[OA\Post(
        path: '/sys/dict-data',
        summary: '创建字典数据',
        tags: ['字典数据'],
        x: [OpenApiModifier::X_REQUEST_BODY => DictDataRequest::class]
    )]
    #[Permission(title: '创建字典数据', code: 'sys:dict:type:create', module: 'system', action: 'create')]
    #[DataResponse()]
    public function create(Request $request): Response
    {
        $data = $this->validator->scene('store')->setPost()->check();
        $result = $this->dictDataService->create($data);
        return R::created($result);
    }

    #[OA\Put(
        path: '/sys/dict-data/{id}',
        summary: '更新字典数据',
        tags: ['字典数据'],
        x: [
            OpenApiModifier::X_PATH_PARAMETERS => [
                'id' => ['type' => 'integer', 'description' => '字典数据ID'],
            ],
            OpenApiModifier::X_REQUEST_BODY => DictDataRequest::class
        ]
    )]
    #[Permission(title: '更新字典数据', code: 'sys:dict:type:update', module: 'system', action: 'update')]
    #[DataResponse()]
    public function update(Request $request, int $id): Response
    {
        $data = $this->validator->scene('update')->setAll()->check();
        $result = $this->dictDataService->update($id, $data);
        return R::data($result, '更新成功');
    }

    #[OA\Delete(
        path: '/sys/dict-data/{id}',
        summary: '删除字典数据',
        tags: ['字典数据'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '字典数据ID'],
        ]]
    )]
    #[Permission(title: '删除字典数据', code: 'sys:dict:type:delete', module: 'system', action: 'delete')]
    #[DataResponse()]
    public function destroy(int $id): Response
    {
        $params = $this->validator->scene('destroy')->setPath()->check();
        $this->dictDataService->delete($params['id']);
        return R::success(null, '删除成功');
    }

    #[OA\Delete(
        path: '/sys/dict-data/batch',
        summary: '批量删除字典数据',
        tags: ['字典数据']
    )]
    #[Permission(title: '批量删除字典数据', code: 'sys:dict:type:delete', module: 'system', action: 'delete')]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        $this->validator->scene('batchDestroy')->setPost()->check();
        $result = $this->dictDataService->batchDelete(request()->input('ids', []));
        return R::success($result, '批量删除成功');
    }
}
