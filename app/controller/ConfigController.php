<?php

namespace plugin\nanoadmin\app\controller;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
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
 */
#[OA\Tag(name: '系统配置', description: '系统配置管理')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class ConfigController extends BaseController
{
    private ConfigService $configService;
    private ConfigValidator $validator;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
        $this->validator = new ConfigValidator();
    }

    protected function getService(): ConfigService
    {
        return $this->configService;
    }

    protected function getModelName(): string
    {
        return 'Config';
    }

    #[OA\Get(
        path: '/sys/config',
        summary: '配置分页列表',
        tags: ['系统配置'],
        x: [SchemaConstants::X_SCHEMA_TO_PARAMETERS => ConfigQuery::class]
    )]
    #[PageResponse(schema: ConfigResponse::class)]
    public function page(Request $request): Response
    {
        try {
            $params = $this->validator->validateData($request->get(), 'page');
            return R::paginate($this->configService->getPage($params));
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取列表失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
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
    #[DataResponse(schema: ConfigItemResponse::class)]
    public function getByGroup(Request $request): Response
    {
        $this->validator->validateRequest('get_by_group');
        $group = (string)$request->get('group', 'basic');
        $configs = $this->configService->getByGroup($group);
        return R::data($configs, '获取配置成功');
    }

    #[OA\Get(
        path: '/sys/config/{id}',
        summary: '配置详情',
        tags: ['系统配置'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '配置ID'],
        ]]
    )]
    #[DataResponse(schema: ConfigResponse::class)]
    public function show(int $id = 0): Response
    {
        try {
            $this->validator->validateId($id);
            return parent::show($id);
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        }
    }

    #[OA\Post(
        path: '/sys/config',
        summary: '创建配置',
        tags: ['系统配置'],
        x: [OpenApiModifier::X_REQUEST_BODY => ConfigRequest::class]
    )]
    #[DataResponse()]
    public function create(Request $request): Response
    {
        try {
            $data = $this->validator->validateData($request->post(), 'store');
            return R::created($this->configService->create($data));
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('创建配置失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
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
    #[DataResponse()]
    public function update(Request $request, int $id): Response
    {
        try {
            $data = $this->validator->validateUpdateData($request->post(), $id);
            return R::data($this->configService->update($id, $data), '更新成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('更新配置失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
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
    #[DataResponse()]
    public function batchUpdate(Request $request): Response
    {
        $this->validator->validateRequest('batch_update');

        $items = $request->post('items', []);
        if (!is_array($items) || empty($items)) {
            return R::error('参数错误：缺少 items 字段', Code::PARAMETER_ERROR->value);
        }
        $count = $this->configService->batchUpdateValues($items);
        return R::success(['updated' => $count], '保存成功');
    }

    #[OA\Delete(
        path: '/sys/config/batch',
        summary: '批量删除配置',
        tags: ['系统配置']
    )]
    #[DataResponse()]
    public function batchDestroy(Request $request): Response
    {
        try {
            $data = $this->validator->validateData($request->post(), 'batch_destroy');
            $result = $this->configService->batchDelete($data['ids']);
            return R::success(['count' => $result], '批量删除配置成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('批量删除配置失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    #[OA\Delete(
        path: '/sys/config/{id}',
        summary: '删除配置',
        tags: ['系统配置'],
        x: [OpenApiModifier::X_PATH_PARAMETERS => [
            'id' => ['type' => 'integer', 'description' => '配置ID'],
        ]]
    )]
    #[DataResponse()]
    public function destroy(int $id): Response
    {
        try {
            $this->validator->validateId($id);
            return parent::destroy($id);
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        }
    }
}
