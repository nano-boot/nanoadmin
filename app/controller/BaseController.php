<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\common\R;
use plugin\theadmin\app\service\BaseService;
use support\Request;
use support\Response;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;

/**
 * 基础控制器类
 * 提供通用的 CRUD 操作
 */
abstract class BaseController
{
    /**
     * 获取分页列表
     * @param Request $request
     * @return Response
     */
    public function page(Request $request): Response
    {
        return R::paginate($this->getService()->getPage($request->all()));
    }

    /**
     * 获取详情
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        try {
            $id = $request->get('id', 0);
            $data = $this->getService()->{'get' . $this->getModelName() . 'ById'}($id);
            return R::success($data, '获取' . $this->getModelName() . '详情成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('获取' . $this->getModelName() . '详情失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 创建记录
     * @param Request $request
     * @param array $fields 创建时允许的字段
     * @return Response
     */
    public function create(Request $request): Response
    {
        try {
            return R::created($this->getService()->{'create'}($request->post()));
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('创建' . $this->getModelName() . '失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 更新记录
     * @param Request $request
     * @param int $id
     * @param array $fields 更新时允许的字段
     * @return Response
     */
    public function update(Request $request, int $id): Response
    {
        try {
            $data = $this->getService()->{'update'}($id, $request->post());
            return R::data($data, '更新成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('更新' . $this->getModelName() . '失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 删除记录
     * @param int $id
     * @return Response
     */
    public function destroy(int $id): Response
    {
        try { 
            $this->getService()->{'delete'}($id);
            return R::deleted('删除成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('删除失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 批量删除记录
     * @param Request $request
     * @return Response
     */
    public function batchDestroy(Request $request): Response
    {
        try {
            $ids = $request->post('ids', []);
            $result = $this->getService()->{'batchDelete' . $this->getModelNames()}($ids);
            return R::success($result, '批量删除' . $this->getModelName() . '成功');
        } catch (ApiException $e) {
            return R::error($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return R::error('批量删除' . $this->getModelName() . '失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }

    /**
     * 获取服务实例
     * @return BaseService
     */
    abstract protected function getService(): BaseService;

    /**
     * 获取模型名称（用于方法名拼接）
     * @return string
     */
    abstract protected function getModelName(): string;

    /**
     * 获取模型复数名称（用于批量操作方法名拼接）
     * @return string
     */
    protected function getModelNames(): string
    {
        return $this->getModelName() . 's';
    }
}
