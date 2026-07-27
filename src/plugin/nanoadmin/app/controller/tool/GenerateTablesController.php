<?php

namespace plugin\nanoadmin\app\controller\tool;

use OpenApi\Attributes as OA;
use plugin\nanoadmin\app\attribute\Permission;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\middleware\AuthMiddleware;
use plugin\nanoadmin\app\middleware\PermissionMiddleware;
use plugin\nanoadmin\app\service\tool\GenerateTablesService;
use plugin\nanoadmin\app\validator\tool\GenerateTablesValidator;
use support\annotation\Middleware;
use support\Request;
use support\Response;

/**
 * 代码生成控制器
 */
#[OA\Tag(name: '代码生成', description: '代码生成管理')]
#[Permission(title: '代码生成管理', code: 'tool:generate')]
#[Middleware(AuthMiddleware::class, PermissionMiddleware::class)]
class GenerateTablesController
{
    private GenerateTablesService $service;
    private GenerateTablesValidator $validator;

    public function __construct(
        GenerateTablesService $service,
        GenerateTablesValidator $validator
    ) {
        $this->service = $service;
        $this->validator = $validator;
    }

    /**
     * 获取代码生成列表
     */
    #[OA\Get(path: '/sys/generate/tables', summary: '代码生成列表')]
    #[OA\Response(response: '200', description: '成功')]
    public function index(Request $request): Response
    {
        try {
            $params = $this->validator->scene('page')->setGet()->check();
            return R::paginate($this->service->getPage($params));
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取列表失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 获取代码生成详情
     */
    #[OA\Get(path: '/sys/generate/tables/{id}', summary: '代码生成详情')]
    #[OA\Response(response: '200', description: '成功')]
    public function read(int $id): Response
    {
        try {
            $params = $this->validator->scene('show')->setPath()->check();
            $data = $this->service->getById($params['id']);
            return R::success($data, '获取详情成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取详情失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 更新代码生成配置
     */
    #[OA\Put(path: '/sys/generate/tables/{id}', summary: '更新代码生成配置')]
    #[OA\Response(response: '200', description: '成功')]
    public function update(Request $request, int $id): Response
    {
        try {
            $params = $this->validator->scene('update')->setAll()->check();
            $data = $this->service->update($params['id'], $params);
            return R::data($data, '更新成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('更新失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 删除代码生成配置
     */
    #[OA\Delete(path: '/sys/generate/tables/{id}', summary: '删除代码生成配置')]
    #[OA\Response(response: '200', description: '成功')]
    public function destroy(int $id): Response
    {
        try {
            $params = $this->validator->scene('destroy')->setPath()->check();
            $this->service->delete($params['id']);
            return R::success(null, '删除成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('删除失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 装载数据库表
     */
    #[OA\Post(path: '/sys/generate/tables/load', summary: '装载数据表')]
    #[OA\Response(response: '200', description: '成功')]
    public function loadTable(Request $request): Response
    {
        try {
            $params = $this->validator->scene('loadTable')->setPost()->check();
            $result = $this->service->loadTable($params['names'], $params['source'] ?? '');
            return R::success($result, '装载完成');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('装载失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 同步表结构
     */
    #[OA\Post(path: '/sys/generate/tables/{id}/sync', summary: '同步表结构')]
    #[OA\Response(response: '200', description: '成功')]
    public function sync(int $id): Response
    {
        try {
            $params = $this->validator->scene('sync')->setPath()->check();
            $result = $this->service->sync($params['id']);
            return R::success($result, '同步成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('同步失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 预览代码
     */
    #[OA\Get(path: '/sys/generate/tables/{id}/preview', summary: '代码预览')]
    #[OA\Response(response: '200', description: '成功')]
    public function preview(int $id): Response
    {
        try {
            $params = $this->validator->scene('preview')->setPath()->check();
            $result = $this->service->preview($params['id']);
            return R::success($result, '获取预览成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('预览失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 生成代码（下载）
     */
    #[OA\Post(path: '/sys/generate/tables/generate', summary: '生成代码')]
    #[OA\Response(response: '200', description: '成功')]
    public function generate(Request $request): Response
    {
        try {
            $params = $this->validator->scene('generate')->setPost()->check();
            return $this->service->generate($params['ids']);
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('生成失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 生成代码到项目
     */
    #[OA\Post(path: '/sys/generate/tables/{id}/generate-file', summary: '生成代码到项目')]
    #[OA\Response(response: '200', description: '成功')]
    public function generateFile(int $id): Response
    {
        try {
            $params = $this->validator->scene('generateFile')->setPath()->check();
            $result = $this->service->generateFile($params['id']);
            return R::success($result, '生成完成');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('生成失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 获取字段配置
     */
    #[OA\Get(path: '/sys/generate/tables/{tableId}/columns', summary: '获取字段配置')]
    #[OA\Response(response: '200', description: '成功')]
    public function getTableColumns(int $tableId): Response
    {
        try {
            $params = $this->validator->scene('getTableColumns')->setPath()->check();
            $result = $this->service->getTableColumns($params['table_id']);
            return R::success($result, '获取成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 获取数据库表列表
     */
    #[OA\Get(path: '/sys/generate/tables/database', summary: '获取数据库表列表')]
    #[OA\Response(response: '200', description: '成功')]
    public function getDatabaseTables(Request $request): Response
    {
        try {
            $params = $this->validator->scene('getDatabaseTables')->setGet()->check();
            $result = $this->service->getDatabaseTables($params['source'] ?? '');
            return R::success($result, '获取成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 保存字段配置
     */
    #[OA\Post(path: '/sys/generate/tables/{tableId}/columns', summary: '保存字段配置')]
    #[OA\Response(response: '200', description: '成功')]
    public function saveColumns(Request $request, int $tableId): Response
    {
        try {
            $data = $request->post();
            $data['table_id'] = $tableId;
            $params = $this->validator->scene('saveColumns')->setData($data)->check();
            $this->service->saveColumns($params['table_id'], $params['columns'] ?? []);
            return R::success(null, '保存成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('保存失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }
}
