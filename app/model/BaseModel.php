<?php

namespace plugin\theadmin\app\model;

use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;
use think\Model;
use think\model\concern\SoftDelete;
use think\Paginator;

/**
 * 基础模型类
 * 提供通用的模型功能和约定
 */
abstract class BaseModel extends Model
{
    use SoftDelete;

    protected function getOptions(): array
    {
        return [
            'createTime'    =>    'created_at',
            'updateTime'    =>    'updated_at',
            'deleteTime'    =>    'deleted',
            'defaultSoftDelete' => 0,
        ];
    }

    /**
     * 全局查询范围 - 排除软删除
     * @param Query $query
     */
    public function scopeActive(Query $query): void
    {
        $query->withTrashed();
    }

    /**
     * 状态范围查询 - 启用状态
     * @param Query $query
     */
    public function scopeEnabled(Query $query): void
    {
        $query->where('status', '=', 1);
    }

    /**
     * 状态范围查询 - 禁用状态
     * @param Query $query
     */
    public function scopeDisabled(Query $query): void
    {
        $query->where('status', '=', 0);
    }

    /**
     * 排序范围查询
     * @param Query $query
     * @param string $field 排序字段
     * @param string $order 排序方向
     */
    public function scopeSort(Query $query, string $field = 'sort', string $order = 'asc'): void
    {
        $query->order($field, $order);
    }

    /**
     * 分页查询
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string $order 排序
     * @return Paginator
     * @throws DbException
     */
    public function getList(array $where = [], int $page = 1, int $limit = 15, string $order = 'id desc'): Paginator
    {
        $query = $this->where($where);

        if ($order) {
            $query->order($order);
        }

        return $query->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
    }

    /**
     * 获取所有记录
     * @param array $where 查询条件
     * @param string $order 排序
     * @param string $field 字段
     * @return Collection
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function getAll(array $where = [], string $order = 'id desc', string $field = '*'): Collection
    {
        $query = $this->field($field)->where($where);

        if ($order) {
            $query->order($order);
        }

        return $query->select();
    }

    /**
     * 根据ID获取记录
     * @param int $id
     * @param string $field 字段
     * @return BaseModel
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getById(int $id, string $field = '*'): BaseModel
    {
        return $this->field($field)->find($id);
    }

    /**
     * 根据条件获取单条记录
     * @param array $where 查询条件
     * @param string $field 字段
     * @return static|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getOne(array $where, string $field = '*'): ?BaseModel
    {
        return $this->field($field)->where($where)->find();
    }

    /**
     * 添加记录
     * @param array $data 数据
     * @return BaseModel|false
     */
    public function add(array $data): BaseModel|bool|static
    {
        return $this->save($data) ? $this : false;
    }

    /**
     * 更新记录
     * @param array $data 数据
     * @param array $where 条件
     * @return bool
     */
    public function edit(array $data, array $where = []): bool
    {
        if (empty($where) && isset($data['id'])) {
            $where = ['id' => $data['id']];
            unset($data['id']);
        }

        return $this->where($where)->update($data) != false;
    }

    /**
     * 删除记录（软删除）
     * @param int|array $id ID或条件
     * @return bool
     */
    public function remove(int|array $id): bool
    {
        if (is_array($id)) {
            return $this->where($id)->delete() !== false;
        }

        return $this->destroy($id) !== false;
    }

    /**
     * 真实删除记录
     * @param int|array $id ID或条件
     * @return bool
     */
    public function forceRemove(int|array $id): bool
    {
        if (is_array($id)) {
            return $this->where($id)->force()->delete() !== false;
        }

        return $this->destroy($id, true) !== false;
    }

    /**
     * 切换状态
     * @param int $id ID
     * @param string $field 字段名
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function toggleStatus(int $id, string $field = 'status'): bool
    {
        $model = $this->find($id);
        if (!$model) {
            return false;
        }

        $model->$field = $model->$field ? 0 : 1;
        return $model->save();
    }

    /**
     * 批量更新排序
     * @param array $data 数据 [['id' => 1, 'sort' => 10], ...]
     * @return bool
     */
    public function updateSort(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $this->startTrans();
        try {
            foreach ($data as $item) {
                if (isset($item['id']) && isset($item['sort'])) {
                    $this->where('id', $item['id'])->update(['sort' => $item['sort']]);
                }
            }
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * 获取下一个排序值
     * @param array $where 查询条件
     * @return int
     */
    public function getNextSort(array $where = []): int
    {
        $maxSort = $this->where($where)->max('sort');
        return $maxSort ? $maxSort + 10 : 100;
    }

    /**
     * 检查记录是否存在
     * @param array $where 查询条件
     * @param int $excludeId 排除的ID
     * @return bool
     */
    public function checkExists(array $where, int $excludeId = 0): bool
    {
        $query = $this->where($where);

        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->count() > 0;
    }
}