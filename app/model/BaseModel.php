<?php

namespace plugin\theadmin\app\model;

use support\Model;

/**
 * 基础模型类
 * 提供通用的模型功能和约定
 */
abstract class BaseModel extends Model
{
    /**
     * 时间戳格式
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';
    
    /**
     * 需要转换的属性类型
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 全局查询范围 - 排除软删除
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeActive($query): void
    {
        $query->withTrashed();
    }

    /**
     * 状态范围查询 - 启用状态
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeEnabled($query): void
    {
        $query->where('status', '=', 1);
    }

    /**
     * 状态范围查询 - 禁用状态
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeDisabled($query): void
    {
        $query->where('status', '=', 0);
    }

    /**
     * 排序范围查询
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field 排序字段
     * @param string $order 排序方向
     */
    public function scopeSort($query, string $field = 'sort', string $order = 'asc'): void
    {
        $query->orderBy($field, $order);
    }

    /**
     * 分页查询
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string $order 排序
     * @return array
     */
    public function getList(array $where = [], int $page = 1, int $limit = 15, string $order = 'id desc'): array
    {
        $query = $this->where($where);

        if ($order) {
            $query->orderByRaw($order);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 获取所有记录
     * @param array $where 查询条件
     * @param string $order 排序
     * @param string $field 字段
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll(array $where = [], string $order = 'id desc', string $field = '*'): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->select($field)->where($where);

        if ($order) {
            $query->orderByRaw($order);
        }

        return $query->get();
    }

    /**
     * 根据ID获取记录
     * @param int $id
     * @param string $field 字段
     * @return BaseModel|null
     */
    public function getById(int $id, string $field = '*'): ?BaseModel
    {
        return $this->select($field)->find($id);
    }

    /**
     * 根据条件获取单条记录
     * @param array $where 查询条件
     * @param string $field 字段
     * @return static|null
     */
    public function getOne(array $where, string $field = '*'): ?BaseModel
    {
        return $this->select($field)->where($where)->first();
    }

    /**
     * 添加记录
     * @param array $data 数据
     * @return BaseModel|false
     */
    public function add(array $data): BaseModel|bool
    {
        return $this->create($data);
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

        return $this->where($where)->update($data);
    }

    /**
     * 删除记录（软删除）
     * @param int|array $id ID或条件
     * @return bool
     */
    public function remove(int|array $id): bool
    {
        if (is_array($id)) {
            return $this->where($id)->delete();
        }

        return $this->destroy($id);
    }

    /**
     * 真实删除记录
     * @param int|array $id ID或条件
     * @return bool
     */
    public function forceRemove(int|array $id): bool
    {
        if (is_array($id)) {
            return $this->where($id)->forceDelete();
        }

        return $this->destroy($id, true);
    }

    /**
     * 切换状态
     * @param int $id ID
     * @param string $field 字段名
     * @return bool
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

        \support\Db::beginTransaction();
        try {
            foreach ($data as $item) {
                if (isset($item['id']) && isset($item['sort'])) {
                    $this->where('id', $item['id'])->update(['sort' => $item['sort']]);
                }
            }
            \support\Db::commit();
            return true;
        } catch (\Exception $e) {
            \support\Db::rollback();
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

        return $query->exists();
    }
}