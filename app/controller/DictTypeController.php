<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\dict\DictTypeQuery;
use plugin\nanoadmin\app\schema\dict\DictTypeRequest;
use plugin\nanoadmin\app\schema\dict\DictTypeResponse;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\Annotation\Response\PageResponse;
use plugin\nanoadmin\app\library\swagger\Annotation\Response\DataResponse;
use plugin\nanoadmin\app\validator\dict\DictTypeValidator;
use plugin\nanoadmin\app\service\DictTypeService;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 字典类型控制器
 *
 * 采用「薄 Controller」模式：
 * - Controller 只负责接收请求、调用验证器、调用 Service、返回响应
 * - 异常由全局异常处理器统一处理
 * - 业务逻辑全部在 Service 层
 * - 路由由 OpenApiRouteRegister 根据本类上的 OA 注解自动注册
 */
#[OA\Tag(name: '字典类型', description: '字典类型管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class DictTypeController extends BaseController
{
    private DictTypeService $dictTypeService;
    private DictTypeValidator $validator;

    public function __construct(DictTypeService $dictTypeService, DictTypeValidator $validator)
    {
        $this->dictTypeService = $dictTypeService;
        $this->validator = $validator;
    }

    #[OA\Get(
        path: '/sys/dict-type',
        summary: '字典类型列表',
        tags: ['字典类型'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => DictTypeQuery::class]
    )]
    #[PageResponse(schema: DictTypeResponse::class)]
    public function page(Request $request): Response
    {
        $params = $this->validator->validateData($request->get(), 'page');
        return R::paginate($this->dictTypeService->getPage($params));
    }

    #[OA\Get(
        path: '/sys/dict-type/{id}',
        summary: '字典类型详情',
        tags: ['字典类型'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '字典类型ID'],
        ]]
    )]
    #[DataResponse(schema: DictTypeResponse::class)]
    public function show(int $id): Response
    {
        $this->validator->validateId($id);
        return R::success($this->dictTypeService->getById($id), '获取详情成功');
    }

    #[OA\Post(
        path: '/sys/dict-type',
        summary: '创建字典类型',
        tags: ['字典类型'],
        x: [OpenApiModifier::X_REQUEST_BODY => DictTypeRequest::class]
    )]
    #[DataResponse()]
    public function create(Request $request): Response
    {
        $data = $this->validator->validateData($request->post(), 'store');
        $result = $this->dictTypeService->create($data);
        return R::created($result);
    }

    #[OA\Put(
        path: '/sys/dict-type/{id}',
        summary: '更新字典类型',
        tags: ['字典类型'],
        x: [
            OpenApiModifier::X_PATH_PARAMETERS => [
                'id' => ['type' => 'integer', 'description' => '字典类型ID'],
            ],
            OpenApiModifier::X_REQUEST_BODY => DictTypeRequest::class,
        ]
    )]
    #[DataResponse()]
    public function update(Request $request, int $id): Response
    {
        $this->validator->validateId($id);
        $data = $this->validator->validateUpdateData($request->post(), $id);
        $result = $this->dictTypeService->update($id, $data);
        return R::data($result, '更新成功');
    }

    #[OA\Delete(
        path: '/sys/dict-type/{id}',
        summary: '删除字典类型',
        tags: ['字典类型'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '字典类型ID'],
        ]]
    )]
    #[DataResponse()]
    public function destroy(int $id): Response
    {
        $this->validator->validateId($id);
        $this->dictTypeService->delete($id);
        return R::success(null, '删除成功');
    }

    #[OA\Delete(
        path: '/sys/dict-type/batch',
        summary: '批量删除字典类型',
        tags: ['字典类型']
    )]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        $data = $this->validator->validateBatchIds($request->post());
        $result = $this->dictTypeService->batchDelete($data['ids']);
        return R::success($result, '批量删除成功');
    }
}