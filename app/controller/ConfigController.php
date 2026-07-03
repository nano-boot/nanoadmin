<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\attribute\Permission;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\schema\config\ConfigBatchUpdateRequest;
use plugin\nanoadmin\app\schema\config\ConfigItemResponse;
use plugin\nanoadmin\app\schema\config\ConfigQuery;
use plugin\nanoadmin\app\schema\config\ConfigRequest;
use plugin\nanoadmin\app\schema\config\ConfigResponse;
use plugin\nanoadmin\app\library\swagger\OpenApiModifier;
use plugin\nanoadmin\app\library\swagger\SchemaConstants;
use plugin\nanoadmin\app\library\swagger\annotation\response\DataResponse;
use plugin\nanoadmin\app\library\swagger\annotation\response\PageResponse;
use plugin\nanoadmin\app\validator\config\ConfigValidator;
use plugin\nanoadmin\app\service\ConfigService;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 系统配置控制器
 *
 * Phase 2 注解化（来源：authorization-refactoring-plan.md §1）：
 *  - 类级 #[Permission] 提供兜底权限码 sys:config
 *  - 方法级 #[Permission] 精确声明每个方法的权限码（与 route_permissions 对齐）
 */
#[OA\Tag(name: '系统配置', description: '系统配置管理')]
#[Permission(title: '系统配置', code: 'sys:config', module: 'system')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class ConfigController extends BaseController
{
    private ConfigService $service;
    private ConfigValidator $validator;

    public function __construct(ConfigService $service, ConfigValidator $validator)
    {
        $this->service = $service;
        $this->validator = $validator;
    }

    #[OA\Get(
        path: '/sys/config',
        summary: '配置分页列表',
        tags: ['系统配置'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => ConfigQuery::class]
    )]
    #[Permission(title: '配置列表', code: 'sys:config:query', module: 'system', action: 'page')]
    #[PageResponse(schema: ConfigResponse::class)]
    public function page(Request $request): Response
    {
        $params = $this->validator->scene('page')->setGet()->check();
        return R::paginate($this->service->getPage($params));
    }

    /**
     * 根据分组获取配置列表（用于表单展示）
     */
    #[OA\Get(
        path: '/sys/config/group',
        summary: '按分组获取配置项',
        description: '根据分组编码获取启用的配置项列表，用于前端表单展示',
        tags: ['系统配置']
    )]
    #[OA\Parameter(
        name: 'group',
        description: '配置分组编码',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'basic')
    )]
    #[Permission(title: '按分组获取配置', code: 'sys:config:query', module: 'system', action: 'page')]
    #[DataResponse(schema: ConfigItemResponse::class)]
    public function getByGroup(Request $request): Response
    {
        $data = $this->validator->scene('getByGroup')->setGet()->check();
        $group = $data['group'] ?? 'basic';
        return R::data($this->service->getByGroup($group), '获取配置成功');
    }

    #[OA\Get(
        path: '/sys/config/{id}',
        summary: '配置详情',
        tags: ['系统配置'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '配置ID'],
        ]]
    )]
    #[Permission(title: '配置详情', code: 'sys:config:query', module: 'system', action: 'page')]
    #[DataResponse(schema: ConfigResponse::class)]
    public function show(int $id): Response
    {
        $params = $this->validator->scene('show')->setPath()->check();
        return R::success($this->service->getById($params['id']), '获取详情成功');
    }

    #[OA\Post(
        path: '/sys/config',
        summary: '创建配置',
        tags: ['系统配置'],
        x: [OpenApiModifier::X_REQUEST_BODY => ConfigRequest::class]
    )]
    #[Permission(title: '创建配置', code: 'sys:config:create', module: 'system', action: 'create')]
    #[DataResponse()]
    public function create(Request $request): Response
    {
        $data = $this->validator->scene('store')->setPost()->check();
        return R::created($this->service->create($data));
    }

    #[OA\Put(
        path: '/sys/config/{id}',
        summary: '更新配置',
        tags: ['系统配置'],
        x: [
            OpenApiModifier::X_PATH_PARAMETERS => [
                'id' => ['type' => 'integer', 'description' => '配置ID'],
            ],
            OpenApiModifier::X_REQUEST_BODY => ConfigRequest::class,
        ]
    )]
    #[Permission(title: '更新配置', code: 'sys:config:update', module: 'system', action: 'update')]
    #[DataResponse()]
    public function update(Request $request, int $id): Response
    {
        $data = $this->validator->scene('update')->setAll()->check();
        return R::data($this->service->update($id, $data), '更新成功');
    }

    /**
     * 批量更新配置值
     */
    #[OA\Put(
        path: '/sys/config/batch',
        summary: '批量更新配置值',
        description: '按 key 批量更新配置 value，常用于配置表单保存',
        tags: ['系统配置'],
        x: [OpenApiModifier::X_REQUEST_BODY => ConfigBatchUpdateRequest::class]
    )]
    #[Permission(title: '批量更新配置', code: 'sys:config:update', module: 'system', action: 'update')]
    #[DataResponse()]
    public function batchUpdate(Request $request): Response
    {
        $data = $this->validator->scene('batchUpdate')->setPost()->check();
        $count = $this->service->batchUpdateValues($data['items'] ?? []);
        return R::success(['updated' => $count], '保存成功');
    }

    #[OA\Delete(
        path: '/sys/config/batch',
        summary: '批量删除配置',
        tags: ['系统配置']
    )]
    #[Permission(title: '批量删除配置', code: 'sys:config:delete', module: 'system', action: 'delete')]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        $data = $this->validator->scene('batchDestroy')->setPost()->check();
        return R::success(['count' => $this->service->batchDelete($data['ids'])], '批量删除成功');
    }

    #[OA\Delete(
        path: '/sys/config/{id}',
        summary: '删除配置',
        tags: ['系统配置'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '配置ID'],
        ]]
    )]
    #[Permission(title: '删除配置', code: 'sys:config:delete', module: 'system', action: 'delete')]
    #[DataResponse()]
    public function destroy(int $id): Response
    {
        $params = $this->validator->scene('destroy')->setPath()->check();
        $this->service->delete($params['id']);
        return R::success(null, '删除成功');
    }
}
