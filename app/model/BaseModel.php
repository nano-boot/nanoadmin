<?php

namespace plugin\theadmin\app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 基础模型类
 * 提供通用的模型功能和约定
 */
abstract class BaseModel extends Model
{
    use SoftDelete;

    /**
     * 软删除字段
     * @var string
     */
    protected $deleteTime = 'deleted';

    /**
     * 软删除字段默认值
     * @var mixed
     */
    protected $defaultSoftDelete = false;

    /**
     * 自动时间戳
     * @var bool|string
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'created_at';

    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'updated_at';

    /**
     * 时间字段取出后的默认时间格式
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 全局查询范围 - 排除软删除
     * @param \think\db\Query $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->where($this->deleteTime, '=', $this->defaultSoftDelete);
    }

    /**
     * 状态范围查询 - 启用状态
     * @param \think\db\Query $query
     * @return void
     */
    public function scopeEnabled($query)
    {
        $query->where('status', '=', 1);
    }

    /**
     * 状态范围查询 - 禁用状态
     * @param \think\db\Query $query
     * @return void
     */
    public function scopeDisabled($query)
    {
        $query->where('status', '=', 0);
    }

    /**
     * 排序范围查询
     * @param \think\db\Query $query
     * @param string $field 排序字段
     * @param string $order 排序方向
     * @return void
     */
    public function scopeSort($query, $field = 'sort', $order = 'asc')
    {
        $query->order($field, $order);
    }

    /**
     * 分页查询
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string $order 排序
     * @return \think\Paginator
     */
    public function getList($where = [], $page = 1, $limit = 15, $order = 'id desc')
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
     * @return \think\Collection
     */
    public function getAll($where = [], $order = 'id desc', $field = '*')
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
     * @return static|null
     */
    public function getById($id, $field = '*')
    {
        return $this->field($field)->find($id);
    }

    /**
     * 根据条件获取单条记录
     * @param array $where 查询条件
     * @param string $field 字段
     * @return static|null
     */
    public function getOne($where, $field = '*')
    {
        return $this->field($field)->where($where)->find();
    }

    /**
     * 添加记录
     * @param array $data 数据
     * @return static|false
     */
    public function add($data)
    {
        return $this->save($data) ? $this : false;
    }

    /**
     * 更新记录
     * @param array $data 数据
     * @param array $where 条件
     * @return bool
     */
    public function edit($data, $where = [])
    {
        if (empty($where) && isset($data['id'])) {
            $where = ['id' => $data['id']];
            unset($data['id']);
        }
        
        return $this->where($where)->update($data) !== false;
    }

    /**
     * 删除记录（软删除）
     * @param int|array $id ID或条件
     * @return bool
     */
    public function remove($id)
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
    public function forceRemove($id)
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
     */
    public function toggleStatus($id, $field = 'status')
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
    public function updateSort($data)
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
    public function getNextSort($where = [])
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
    public function checkExists($where, $excludeId = 0)
    {
        $query = $this->where($where);
        
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        
        return $query->count() > 0;
    }
}