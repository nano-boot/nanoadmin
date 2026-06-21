<?php

namespace plugin\nanoadmin\app\controller;

use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use support\Request;
use support\Response;

/**
 * 基础控制器类（可选继承）
 *
 * 提供通用的 CRUD 操作基础实现。
 * 子类可选择继承并实现 getService()/getModelName() 使用默认实现，
 * 也可自行实现全部方法。
 *
 * 采用「薄 Controller」模式后，建议每个 Controller 完全自行实现。
 */
class BaseController
{
    /**
     * 获取分页列表（默认实现）
     */
    public function page(Request $request): Response
    {
        try {
            return R::paginate($this->getService()->getPage($request->all()));
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取列表失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 获取详情（默认实现）
     */
    public function show(int $id): Response
    {
        try {
            $data = $this->getService()->getById($id);
            return R::success($data, '获取详情成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取详情失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 创建记录（默认实现）
     */
    public function create(Request $request): Response
    {
        try {
            $modelName = method_exists($this, 'getModelName') ? $this->getModelName() : '记录';
            return R::created($this->getService()->create($request->post()));
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            $modelName = method_exists($this, 'getModelName') ? $this->getModelName() : '记录';
            return R::error('创建' . $modelName . '失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 更新记录（默认实现）
     */
    public function update(Request $request, int $id): Response
    {
        try {
            $data = $this->getService()->update($id, $request->post());
            return R::data($data, '更新成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            $modelName = method_exists($this, 'getModelName') ? $this->getModelName() : '记录';
            return R::error('更新' . $modelName . '失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 删除记录（默认实现）
     */
    public function destroy(int $id): Response
    {
        try {
            $this->getService()->delete($id);
            return R::success(null, '删除成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('删除失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 批量删除记录（默认实现）
     */
    public function batchDestroy(Request $request): Response
    {
        try {
            $ids = $request->post('ids', []);
            $modelName = method_exists($this, 'getModelName') ? $this->getModelName() : '';
            $result = $this->getService()->{'batchDelete' . $modelName}($ids);
            return R::success($result, '批量删除成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            $modelName = method_exists($this, 'getModelName') ? $this->getModelName() : '记录';
            return R::error('批量删除' . $modelName . '失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 获取服务实例（子类可选实现）
     */
    protected function getService()
    {
        throw new \RuntimeException('子类未实现 getService() 方法');
    }

    /**
     * 获取模型名称（子类可选实现，用于错误消息拼接）
     */
    protected function getModelName(): string
    {
        return '';
    }
}
