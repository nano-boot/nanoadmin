<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\permission\PermissionQuery;
use plugin\nanoadmin\app\schema\permission\PermissionRequest;
use plugin\nanoadmin\app\schema\permission\PermissionResponse;
use plugin\nanoadmin\app\service\PermissionService;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\validator\PermissionValidator;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 权限控制器
 *
 */
#[OA\Tag(name: '权限', description: '权限管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class PermissionController extends BaseController
{
    private PermissionService $service;
    private PermissionValidator $validator;

    public function __construct(PermissionService $service, PermissionValidator $validator)
    {
        $this->service = $service;
        $this->validator = $validator;
    }

    /**
     * 获取权限列表
     */
    #[OA\Get(
        path: '/sys/permissions',
        summary: '权限列表',
        tags: ['权限'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => PermissionQuery::class]
    )]
    #[PageResponse(schema: PermissionResponse::class)]
    public function page(Request $request): Response
    {
        $params = $this->validator->scene('page')->setGet()->check();
        return R::paginate($this->service->getPermissionList($params));
    }

    /**
     * 获取权限详情
     */
    #[OA\Get(
        path: '/sys/permissions/{id}',
        summary: '权限详情',
        tags: ['权限'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '权限ID'],
        ]]
    )]
    #[DataResponse(schema: PermissionResponse::class)]
    public function show(int $id): Response
    {
        $params = $this->validator->scene('show')->setPath()->check();
        return R::success($this->service->getPermissionById($params['id']), '获取详情成功');
    }

    /**
     * 创建权限
     */
    #[OA\Post(
        path: '/sys/permissions',
        summary: '创建权限',
        tags: ['权限'],
        x: [OpenApiModifier::X_REQUEST_BODY => PermissionRequest::class]
    )]
    #[DataResponse()]
    public function store(Request $request): Response
    {
        $data = $this->validator->scene('store')->setPost()->check();
        return R::created($this->service->createPermission($data), '创建成功');
    }

    /**
     * 更新权限
     */
    #[OA\Put(
        path: '/sys/permissions/{id}',
        summary: '更新权限',
        tags: ['权限'],
        x: [
            OpenApiModifier::X_PATH_PARAMETERS => [
                'id' => ['type' => 'integer', 'description' => '权限ID'],
            ],
            OpenApiModifier::X_REQUEST_BODY => PermissionRequest::class
        ]
    )]
    #[DataResponse()]
    public function update(Request $request, int $id): Response
    {
        $data = $this->validator->scene('update')->setAll()->check();
        return R::data($this->service->updatePermission($id, $data), '更新成功');
    }

    /**
     * 删除权限
     */
    #[OA\Delete(
        path: '/sys/permissions/{id}',
        summary: '删除权限',
        tags: ['权限'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '权限ID'],
        ]]
    )]
    #[DataResponse()]
    public function destroy(int $id): Response
    {
        $params = $this->validator->scene('destroy')->setPath()->check();
        $this->service->deletePermission($params['id']);
        return R::success(null, '删除成功');
    }

    /**
     * 批量删除权限
     */
    #[OA\Delete(
        path: '/sys/permissions/batch',
        summary: '批量删除权限',
        tags: ['权限']
    )]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        $params = $this->validator->scene('batchDestroy')->setPost()->check();
        $this->service->batchDeletePermissions($params['ids']);
        return R::success(null, '批量删除成功');
    }
}
