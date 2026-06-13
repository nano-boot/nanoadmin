<?php

namespace plugin\nanoadmin\app\model;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use support\Model;
use Illuminate\Support\Arr;

/**
 * 基础模型类
 * 提供通用的模型功能和约定
 */
abstract class BaseModel extends Model
{
    /**
     * 列表默认排序规则（约定/配置化）
     *
     * 子类可覆盖此属性来声明默认排序字段；BaseService 会优先使用它，避免运行时查表结构。
     *
     * 示例：
     *  protected static array $defaultOrder = [
     *      ['id', 'desc'],
     *  ];
     *
     * 也可以禁用默认排序：
     *  protected static array $defaultOrder = [];
     */
    protected static array $defaultOrder = [
        ['id', 'desc'],
    ];

    /**
     * 时间戳格式
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 属性默认值
     * @var array
     */
    protected $attributes = [];

    /**
     * 需要转换的属性类型
     * @var array
     */
    protected $casts = [
        'status' => 'integer',
        'sort' => 'integer',
        'deleted' => 'boolean',
        'created_at' => 'datetime:Y-m-d',
        'updated_at' => 'datetime:Y-m-d',
    ];

    /**
     * 搜索字段分组（LIKE 类型），子类可覆盖
     * @var array
     */
    protected static array $searchLikeFields = [];

    /**
     * 搜索字段分组（等值类型），子类可覆盖
     * @var array
     */
    protected static array $searchEqualFields = [];

    /**
     * keyword 关键字搜索作用的字段（会对这些字段做 OR LIKE），子类可覆盖
     * @var array
     */
    protected static array $searchKeywordFields = [];

    /**
     * 配置化的范围字段列表
     * @var array
     */
    protected static array $searchRangeFields = [];

     /**
     * 设置通用的模型事件
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BaseModel $model) {
            if ($model->timestamps === false) {
                return;
            }

            $now = date('Y-m-d H:i:s');
            if (!isset($model->created_at)) {
                $model->created_at = $now;
            }
            if (!isset($model->updated_at)) {
                $model->updated_at = $now;
            }
        });

        static::updating(function (BaseModel $model) {
            if ($model->timestamps === false) {
                return;
            }

            $model->updated_at = date('Y-m-d H:i:s');
        });
    }

    /**
     * 获取范围字段列表
     * @return array
     */
    public static function getSearchRangeFields(): array
    {
        return static::$searchRangeFields ?? [];
    }

    /**
     * 设置范围字段列表
     * @param array $fields
     * @return void
     */
    public static function setSearchRangeFields(array $fields): void
    {
        static::$searchRangeFields = $fields;
    }

    /**
     * 获取 LIKE 类型的搜索字段
     * @return array
     */
    public static function getSearchLikeFields(): array
    {
        return static::$searchLikeFields ?? [];
    }

    /**
     * 设置 LIKE 类型的搜索字段
     * @param array $fields
     * @return void
     */
    public static function setSearchLikeFields(array $fields): void
    {
        static::$searchLikeFields = $fields;
    }

    /**
     * 获取等值搜索字段
     * @return array
     */
    public static function getSearchEqualFields(): array
    {
        return static::$searchEqualFields ?? [];
    }

    /**
     * 设置等值搜索字段
     * @param array $fields
     * @return void
     */
    public static function setSearchEqualFields(array $fields): void
    {
        static::$searchEqualFields = $fields;
    }

    /**
     * 获取 keyword 搜索作用字段
     * @return array
     */
    public static function getSearchKeywordFields(): array
    {
        return static::$searchKeywordFields ?? [];
    }

    /**
     * 设置 keyword 搜索作用字段
     * @param array $fields
     * @return void
     */
    public static function setSearchKeywordFields(array $fields): void
    {
        static::$searchKeywordFields = $fields;
    }

    /**
     * 获取列表默认排序规则（约定/配置化）
     */
    public static function getDefaultOrder(): array
    {
        return static::$defaultOrder ?? [];
    }

    /**
     * 设置列表默认排序规则（运行时配置）
     */
    public static function setDefaultOrder(array $orders): void
    {
        static::$defaultOrder = $orders;
    }

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

    
    public function handleSearch(Builder $query, array $params): Builder
    {
        // 通用的搜索字段分组（可由子类覆盖静态属性）
        $likeFields = static::getSearchLikeFields();
        $equalFields = static::getSearchEqualFields();
        $keywordFields = static::getSearchKeywordFields();
        $rangeFields = static::getSearchRangeFields();
        $searchParams = $params;
        unset($searchParams['page'], $searchParams['limit']);
    
        $keyword = Arr::get($searchParams, 'keyword', null);
        if (!is_null($keyword) && $keyword !== '') {
            $query->where(function (Builder $q) use ($keyword, $keywordFields) {
                foreach ($keywordFields as $field) {
                    $q->orWhere($field, 'like', '%' . $keyword . '%');
                }
            });
        }

        // like 类型字段（分别按字段做 LIKE 查询）
        foreach ($likeFields as $field) {
            $value = Arr::get($searchParams, $field);
            if (!is_null($value) && $value !== '') {
                $query->where($field, 'like', '%' . $value . '%');
            }
        }

        // 等值字段（存在即按等值过滤）
        foreach ($equalFields as $field) {
            if (Arr::exists($searchParams, $field)) {
                $query->where($field, Arr::get($searchParams, $field));
            }
        }

        // ID 列表筛选，优先识别通用 'ids'，其次识别 '{table}_ids'
        $ids = Arr::get($searchParams, 'ids', null);
        if (is_null($ids)) {
            $tableIdsKey = $this->getTable() . '_ids';
            $ids = Arr::get($searchParams, $tableIdsKey, null);
        }
        if (!is_null($ids)) {
            $query->whereIn('id', $ids);
        }

        foreach ($rangeFields as $field) {
            if (!Arr::exists($searchParams, $field)) {
                continue;
            }

            $value = Arr::get($searchParams, $field);
            if (is_array($value) && count($value) === 2 && $value[0] !== '' && $value[1] !== '') {
                $start = $value[0];
                $end = $value[1];
                $query->whereBetween($field, [$start, $end]);
            }
        }

        // 排序参数支持：优先使用 sort_field + sort_order，然后使用 order（raw）
        $sortField = Arr::get($searchParams, 'sort_field', null);
        $sortOrder = Arr::get($searchParams, 'sort_order', 'desc');
        if (!is_null($sortField) && trim($sortField) !== '') {
            $query->orderBy($sortField, $sortOrder);
        } elseif ($rawOrder = Arr::get($searchParams, 'order', null)) {
            $query->orderByRaw($rawOrder);
        }

        return $query;
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
     * @return Collection|\Illuminate\Support\Collection
     */
    public function getAll(array $where = [], string $order = 'id desc', string $field = '*'): Collection|\Illuminate\Support\Collection
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