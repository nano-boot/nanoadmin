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
     * 缓存数据表字段存在性判断结果
     * key: connectionName.table.column
     * value: bool
     */
    protected static array $columnExistsCache = [];

    /**
     * 分页查询输出字段，默认返回全部字段。
     * 子类可覆盖此属性来限制输出字段，例如：
     *   protected static array $selectFields = ['id', 'username', 'created_at'];
     */
    protected static array $selectFields = ['*'];

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

        // 按子类（或基类默认）配置的字段列表过滤输出
        $query->select(static::$selectFields);

        // 默认排序：优先使用模型约定/配置的默认排序；未配置时再做字段存在性兜底
        $defaultOrders = $this->model::getDefaultOrder();
        if (!empty($defaultOrders)) {
            foreach ($defaultOrders as $order) {
                $field = $order[0] ?? null;
                if (!$field) {
                    continue;
                }
                $direction = strtolower((string)($order[1] ?? 'asc'));
                $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

                $query->orderBy((string) $field, $direction);
            }
        } else {
            $query->orderByDesc('id');
        }

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

        // 规范化 IDs：确保是简单的一维数字数组
        $normalizedIds = [];
        foreach ($ids as $id) {
            if (is_array($id)) {
                foreach ($id as $subId) {
                    if (is_numeric($subId)) {
                        $normalizedIds[] = (int) $subId;
                    }
                }
            } elseif (is_numeric($id)) {
                $normalizedIds[] = (int) $id;
            }
        }
        $normalizedIds = array_unique($normalizedIds);

        if (empty($normalizedIds)) {
            throw new ApiException(Code::PARAMETER_ERROR, '请选择要删除的记录');
        }

        // 检查记录是否存在
        $existingRecords = $this->model->whereIn('id', $normalizedIds)->pluck('id')->toArray();
        $invalidIds = array_diff($normalizedIds, $existingRecords);

        if (!empty($invalidIds)) {
            throw new ApiException($this->getNotFoundCode(), $this->getNotFoundMessage() . ': ' . implode(',', $invalidIds));
        }

        try {
            $result = $this->model->destroy($normalizedIds);

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

    /**
     * 判断当前模型对应表是否存在某字段（带静态缓存，减少 information_schema 查询）
     */
    protected function tableHasColumn(string $column): bool
    {
        $connection = $this->model->getConnection();
        $connectionName = method_exists($connection, 'getName') ? (string) $connection->getName() : (string) $connection->getConfig('name');
        $table = $this->model->getTable();

        $cacheKey = $connectionName . '.' . $table . '.' . $column;
        if (array_key_exists($cacheKey, static::$columnExistsCache)) {
            return static::$columnExistsCache[$cacheKey];
        }

        $exists = $connection->getSchemaBuilder()->hasColumn($table, $column);
        static::$columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }
}
