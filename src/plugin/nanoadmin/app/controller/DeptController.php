<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\attribute\Permission;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\dept\DeptQuery;
use plugin\nanoadmin\app\schema\dept\DeptRequest;
use plugin\nanoadmin\app\schema\dept\DeptResponse;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\validator\dept\DeptValidator;
use plugin\nanoadmin\app\service\DeptService;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 部门控制器
 */
#[OA\Tag(name: '部门', description: '部门管理')]
#[Permission(title: '部门管理', code: 'sys:dept')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class DeptController extends BaseController
{
    private DeptService $deptService;
    private DeptValidator $validator;

    public function __construct(DeptService $deptService, DeptValidator $validator)
    {
        $this->deptService = $deptService;
        $this->validator = $validator;
    }

    protected function getService(): DeptService
    {
        return $this->deptService;
    }

    protected function getModelName(): string
    {
        return 'Dept';
    }

    #[OA\Get(
        path: '/sys/dept',
        summary: '部门列表',
        tags: ['部门'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => DeptQuery::class]
    )]
    #[Permission(title: '部门列表', code: 'sys:dept:query', action: 'query')]
    #[PageResponse(schema: DeptResponse::class)]
    public function page(Request $request): Response
    {
        $params = $this->validator->scene('page')->setGet()->check();
        return R::paginate($this->deptService->getPage($params));
    }

    #[OA\Get(
        path: '/sys/dept/tree',
        summary: '部门树形列表',
        description: '获取部门树形结构，支持按关键词、名称、编码、状态、父部门等条件搜索，搜索时通过 BFS 加载祖先以保持树形结构',
        tags: ['部门'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => DeptQuery::class]
    )]
    #[Permission(title: '部门树形列表', code: 'sys:dept:tree', action: 'query')]
    #[DataResponse(schema: DeptResponse::class)]
    public function tree(Request $request): Response
    {
        $params = $this->validator->scene('tree')->setGet()->check();
        return R::success($this->deptService->getDeptTreeWithSearch($params), '获取部门树成功');
    }

    #[OA\Get(
        path: '/sys/dept/select',
        summary: '部门下拉列表',
        tags: ['部门']
    )]
    #[Permission(title: '部门下拉列表', code: 'sys:dept:select', action: 'query')]
    #[DataResponse(schema: DeptResponse::class)]
    public function selectList(): Response
    {
        $list = $this->deptService->getSelectList();
        return R::success($list, '获取下拉列表成功');
    }

    #[OA\Get(
        path: '/sys/dept/{id}',
        summary: '部门详情',
        tags: ['部门']
    )]
    #[Permission(title: '部门详情', code: 'sys:dept:show', action: 'query')]
    #[DataResponse(schema: DeptResponse::class)]
    public function show(int $id): Response
    {
        $this->validator->scene('show')->setPath()->check();
        $data = $this->deptService->getById($id);
        return R::success($data, '获取详情成功');
    }

    #[OA\Post(
        path: '/sys/dept',
        summary: '创建部门',
        tags: ['部门'],
        x: [OpenApiModifier::X_REQUEST_BODY => DeptRequest::class]
    )]
    #[Permission(title: '创建部门', code: 'sys:dept:create', action: 'create')]
    #[DataResponse()]
    public function create(Request $request): Response
    {
        $data = $this->validator->scene('store')->setPost()->check();
        $result = $this->deptService->createDept($data);
        return R::created($result);
    }

    #[OA\Put(
        path: '/sys/dept/{id}',
        summary: '更新部门',
        tags: ['部门'],
        x: [OpenApiModifier::X_REQUEST_BODY => DeptRequest::class]
    )]
    #[Permission(title: '更新部门', code: 'sys:dept:update', action: 'update')]
    #[DataResponse()]
    public function update(Request $request, int $id): Response
    {
        $data = $this->validator
            ->scene('update')
            ->setAll()
            ->check();
        $this->deptService->updateDept($id, $data);
        return R::ok('更新成功');
    }

    #[OA\Delete(
        path: '/sys/dept/{id}',
        summary: '删除部门',
        tags: ['部门']
    )]
    #[Permission(title: '删除部门', code: 'sys:dept:delete', action: 'delete')]
    #[DataResponse()]
    public function delete(int $id): Response
    {
        $this->validator->scene('destroy')->setPath()->check();
        $this->deptService->deleteDept($id);
        return R::ok('删除成功');
    }

    #[OA\Delete(
        path: '/sys/dept/batch',
        summary: '批量删除部门',
        tags: ['部门']
    )]
    #[Permission(title: '批量删除部门', code: 'sys:dept:batch-delete', action: 'delete')]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        $this->validator->scene('batchDestroy')->setPost()->check();
        $ids = $request->post('ids', []);
        $result = $this->deptService->batchDelete($ids);
        return R::success($result, '批量删除成功');
    }
}
