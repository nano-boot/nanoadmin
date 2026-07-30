<?php

namespace plugin\nanoadmin\app\service;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\model\Dept;

/**
 * 部门服务类
 */
class DeptService extends BaseService
{
    /**
     * 构造函数
     * @param Dept $model
     */
    public function __construct(Dept $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取记录不存在时的错误代码
     * @return Code
     */
    protected function getNotFoundCode(): Code
    {
        return Code::DEPT_NOT_FOUND;
    }

    /**
     * 获取记录不存在时的错误消息
     * @return string
     */
    protected function getNotFoundMessage(): string
    {
        return '部门不存在';
    }

    /**
     * 获取部门列表（分页）
     * @param array $params 查询参数
     * @return LengthAwarePaginator
     */
    public function getPage(array $params = []): LengthAwarePaginator
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = min(1000, max(1, (int)($params['limit'] ?? 15)));

        $query = $this->model->handleSearch($this->model->query(), $params);
        $query->select(static::$selectFields);

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
            $query->orderBy('sort', 'asc')->orderBy('id', 'asc');
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * 获取部门树形结构
     * @param int|null $parentId 父部门ID
     * @param bool $onlyEnabled 是否只获取启用的部门
     * @return array
     */
    public function getTree(?int $parentId = 0, bool $onlyEnabled = true): array
    {
        return $this->model->getTree($parentId ?? 0, $onlyEnabled);
    }

    /**
     * 获取部门下拉列表
     * @return Collection
     */
    public function getSelectList(): Collection
    {
        return $this->model->getEnabledList();
    }

    /**
     * 创建部门
     * @param array $data 部门数据
     * @return Dept
     * @throws ApiException
     */
    public function createDept(array $data): Dept
    {
        $parentId = $data['parent_id'] ?? 0;

        // 验证父部门是否存在
        if ($parentId > 0) {
            $parent = $this->model->find($parentId);
            if (!$parent) {
                throw new ApiException(Code::DEPT_NOT_FOUND, '父部门不存在');
            }
        }

        // 计算 path
        $data['path'] = $this->model->calculatePath($parentId);

        // 设置排序值
        if (!isset($data['sort'])) {
            $data['sort'] = $this->model->getNextSort($parentId);
        }

        return $this->create($data);
    }

    /**
     * 更新部门
     * @param int $id 部门ID
     * @param array $data 更新数据
     * @return Dept
     * @throws ApiException
     */
    public function updateDept(int $id, array $data): Dept
    {
        $dept = $this->model->find($id);
        if (!$dept) {
            throw new ApiException(Code::DEPT_NOT_FOUND, '部门不存在');
        }

        $newParentId = $data['parent_id'] ?? $dept->parent_id;

        // 不能将自己设为父部门
        if ($newParentId == $id) {
            throw new ApiException(Code::INVALID_PARAMETER, '不能将部门设为自己的子部门');
        }

        // 检查循环引用
        if ($newParentId > 0 && $this->model->wouldCreateCircularReference($id, $newParentId)) {
            throw new ApiException(Code::INVALID_PARAMETER, '不能将部门移动到自己的子部门下');
        }

        // 如果父部门发生变化，重新计算 path
        if ($newParentId != $dept->parent_id) {
            $newPath = $this->model->calculatePath($newParentId);
            $data['path'] = $newPath;

            // 更新所有子孙部门的 path
            $this->model->updateDescendantPaths($id, $newPath);
        }

        return $this->update($id, $data);
    }

    /**
     * 删除部门
     * @param int $id 部门ID
     * @return bool
     * @throws ApiException
     */
    public function deleteDept(int $id): bool
    {
        $dept = $this->model->find($id);
        if (!$dept) {
            throw new ApiException(Code::DEPT_NOT_FOUND, '部门不存在');
        }

        // 检查是否有子部门
        if ($this->model->hasChildren($id)) {
            throw new ApiException(Code::DATA_HAS_CHILDREN, '该部门下存在子部门，无法删除');
        }

        return $this->delete($id);
    }

    /**
     * 批量删除部门
     * @param array $ids 部门ID数组
     * @return int 删除数量
     * @throws ApiException
     */
    public function batchDelete(array $ids): int
    {
        $failedDepts = [];

        foreach ($ids as $id) {
            if ($this->model->hasChildren($id)) {
                $dept = $this->model->find($id);
                $failedDepts[] = $dept ? "「{$dept->name}」" : "ID:{$id}";
            }
        }

        if (!empty($failedDepts)) {
            throw new ApiException(
                Code::DATA_HAS_CHILDREN,
                '以下部门存在子部门，无法删除：' . implode('、', $failedDepts)
            );
        }

        return $this->batchDeleteRecords($ids);
    }

    /**
     * 获取所有子孙部门ID
     * @param int $deptId 部门ID
     * @return array
     */
    public function getDescendantIds(int $deptId): array
    {
        return $this->model->getDescendantIds($deptId);
    }

    /**
     * 获取部门完整路径名称
     * @param int $deptId 部门ID
     * @return string
     */
    public function getFullPathName(int $deptId): string
    {
        return $this->model->getFullPathName($deptId);
    }
}
