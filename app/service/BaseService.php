<?php

namespace plugin\theadmin\app\service;

use Illuminate\Pagination\LengthAwarePaginator;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\BaseModel;

/**
 * 基础服务类
 * 提供通用的 CRUD 操作
 */
abstract class BaseService
{
    /**
     * 模型实例
     * @var BaseModel
     */
    protected BaseModel $model;

    /**
     * 构造函数
     * @param BaseModel $model 模型实例
     */
    public function __construct(BaseModel $model)
    {
        $this->model = $model;
    }

    /**
     * 获取模型实例
     * @return BaseModel
     */
    public function getModel(): BaseModel
    {
        return $this->model;
    }

    /**
     * 获取分页列表
     * @param array $params 查询参数
     *  - page: 页码（默认1）
     *  - limit: 每页数量（默认15）
     * @return LengthAwarePaginator
     */
    public function getPage(array $params = []): LengthAwarePaginator
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(1000, max(1, (int)($params['limit'] ?? 15)));
        $query = $this->model->handleSearch($this->model->query(), $params);
        return $query->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * 根据ID获取记录详情
     * @param int $id 记录ID
     * @return BaseModel
     * @throws ApiException
     */
    public function getById(int $id): BaseModel
    {
        $record = $this->model->find($id);

        if (!$record) {
            throw new ApiException($this->getNotFoundCode(), $this->getNotFoundMessage());
        }

        return $record;
    }

    /**
     * 创建记录
     * @param array $data 创建数据
     * @return BaseModel
     * @throws ApiException
     */
    public function create(array $data): BaseModel
    {
        try {
            $record = $this->model->create($data);

            if (!$record) {
                throw new ApiException(Code::SYSTEM_ERROR, $this->getCreateFailedMessage());
            }

            return $record;
        } catch (\Exception $e) {
            throw new ApiException(Code::SYSTEM_ERROR, $this->getCreateFailedMessage() . ': ' . $e->getMessage());
        }
    }

    /**
     * 更新记录
     * @param int $id 记录ID
     * @param array $data 更新数据
     * @return BaseModel
     * @throws ApiException
     */
    public function update(int $id, array $data): BaseModel
    {
        $record = $this->model->find($id);

        if (!$record) {
            throw new ApiException($this->getNotFoundCode(), $this->getNotFoundMessage());
        }

        try {
            $record->update($data);
            return $record->fresh();
        } catch (\Exception $e) {
            throw new ApiException(Code::SYSTEM_ERROR, $this->getUpdateFailedMessage() . ': ' . $e->getMessage());
        }
    }

    /**
     * 删除记录
     * @param int $id 记录ID
     * @return bool
     * @throws ApiException
     */
    public function delete(int $id): bool
    {
        $record = $this->model->find($id);

        if (!$record) {
            throw new ApiException($this->getNotFoundCode(), $this->getNotFoundMessage());
        }

        try {
            return $this->model->destroy($id);
        } catch (\Exception $e) {
            throw new ApiException(Code::SYSTEM_ERROR, $this->getDeleteFailedMessage() . ': ' . $e->getMessage());
        }
    }

    /**
     * 批量删除记录
     * @param array $ids 记录ID数组
     * @return int 删除数量
     * @throws ApiException
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            throw new ApiException(Code::PARAMETER_ERROR, '请选择要删除的记录');
        }

        // 检查记录是否存在
        $existingRecords = $this->model->whereIn('id', $ids)->pluck('id')->toArray();
        $invalidIds = array_diff($ids, $existingRecords);

        if (!empty($invalidIds)) {
            throw new ApiException($this->getNotFoundCode(), $this->getNotFoundMessage() . ': ' . implode(',', $invalidIds));
        }

        try {
            $result = $this->model->destroy($ids);

            if ($result === false) {
                throw new ApiException(Code::SYSTEM_ERROR, $this->getBatchDeleteFailedMessage());
            }

            return $result;
        } catch (\Exception $e) {
            throw new ApiException(Code::SYSTEM_ERROR, $this->getBatchDeleteFailedMessage() . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取记录不存在时的错误代码
     * @return Code
     */
    abstract protected function getNotFoundCode(): Code;

    /**
     * 获取记录不存在时的错误消息
     * @return string
     */
    abstract protected function getNotFoundMessage(): string;

    /**
     * 获取创建失败时的错误消息
     * @return string
     */
    protected function getCreateFailedMessage(): string
    {
        return '创建记录失败';
    }

    /**
     * 获取更新失败时的错误消息
     * @return string
     */
    protected function getUpdateFailedMessage(): string
    {
        return '更新记录失败';
    }

    /**
     * 获取删除失败时的错误消息
     * @return string
     */
    protected function getDeleteFailedMessage(): string
    {
        return '删除记录失败';
    }

    /**
     * 获取批量删除失败时的错误消息
     * @return string
     */
    protected function getBatchDeleteFailedMessage(): string
    {
        return '批量删除记录失败';
    }
}
