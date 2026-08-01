<?php

namespace plugin\nanoadmin\app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 部门模型
 */
class Dept extends BaseModel
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'sys_dept';

    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 搜索字段配置
     * @var array
     */
    protected static array $searchLikeFields = ['name', 'code'];
    protected static array $searchEqualFields = ['status', 'parent_id'];
    protected static array $searchKeywordFields = ['name', 'code'];
    protected static array $searchRangeFields = [];

    protected $fillable = [
        'parent_id',
        'path',
        'name',
        'code',
        'phone',
        'email',
        'sort',
        'status',
        'deleted_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'deleted_at' => 'integer',
    ];

    /**
     * 关联父部门
     * @return HasOne
     */
    public function parent(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'parent_id');
    }

    /**
     * 关联子部门
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * @param int $parentId 树的根节点父ID（0 = 全树）
     * @param bool $onlyEnabled 是否只获取启用的部门
     * @param string|null $keyword 部门名称或编码关键词
     * @return array
     */
    public function getTree(int $parentId = 0, bool $onlyEnabled = true, ?string $keyword = null): array
    {
        $query = $this->where('deleted_at', 0);

        if ($onlyEnabled) {
            $query->where('status', 1);
        }

        if ($keyword !== null && trim($keyword) !== '') {
            $keyword = trim($keyword);
            $query->where(function ($query) use ($keyword) {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('code', 'like', '%' . $keyword . '%');
            });
        }

        $allDepts = $query->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        $deptMap = [];
        foreach ($allDepts as $dept) {
            $dept['children'] = [];
            $deptMap[$dept['id']] = $dept;
        }

        $tree = [];
        foreach ($deptMap as &$dept) {
            if ($dept['parent_id'] == $parentId) {
                $tree[] = &$dept;
            } elseif (isset($deptMap[$dept['parent_id']])) {
                $deptMap[$dept['parent_id']]['children'][] = &$dept;
            }
        }
        unset($dept);

        return $tree;
    }

    /**
     * 获取所有子孙部门ID
     * @param int $deptId 部门ID
     * @return array
     */
    public function getDescendantIds(int $deptId): array
    {
        $dept = $this->find($deptId);
        if (!$dept) {
            return [];
        }

        return $this->where('path', 'LIKE', $dept->path . $deptId . ',%')
            ->where('deleted_at', 0)
            ->pluck('id')
            ->toArray();
    }

    /**
     * 获取所有子孙部门
     * @param int $deptId 部门ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDescendants(int $deptId)
    {
        $dept = $this->find($deptId);
        if (!$dept) {
            return collect([]);
        }

        return $this->where('path', 'LIKE', $dept->path . $deptId . ',%')
            ->where('deleted_at', 0)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * 检查部门是否有子部门
     * @param int $id 部门ID
     * @return bool
     */
    public function hasChildren(int $id): bool
    {
        return $this->where('parent_id', $id)
            ->where('deleted_at', 0)
            ->count() > 0;
    }

    /**
     * 检查是否会形成循环引用
     * @param int $deptId 当前部门ID
     * @param int $parentId 要设置的父部门ID
     * @return bool
     */
    public function wouldCreateCircularReference(int $deptId, int $parentId): bool
    {
        if ($parentId == 0) {
            return false;
        }

        if ($deptId == $parentId) {
            return true;
        }

        $currentParentId = $parentId;
        $visited = [];

        while ($currentParentId > 0) {
            if (in_array($currentParentId, $visited)) {
                break;
            }

            $visited[] = $currentParentId;

            if ($currentParentId == $deptId) {
                return true;
            }

            $parent = $this->find($currentParentId);
            $currentParentId = $parent ? $parent->parent_id : 0;
        }

        return false;
    }

    /**
     * 计算 path 字段
     * @param int $parentId 父部门ID
     * @return string
     */
    public function calculatePath(int $parentId): string
    {
        if ($parentId == 0) {
            return ',0,';
        }

        $parent = $this->find($parentId);
        return $parent ? $parent->path . $parentId . ',' : ',0,';
    }

    /**
     * 更新所有子孙部门的 path
     * @param int $deptId 部门ID
     * @param string $newPath 新的 path
     * @return bool
     */
    public function updateDescendantPaths(int $deptId, string $newPath): bool
    {
        $descendants = $this->getDescendants($deptId);

        foreach ($descendants as $descendant) {
            $oldPathPrefix = $descendant->path;
            $relativePath = str_replace($this->path . $deptId . ',', '', $oldPathPrefix);
            $descendant->path = $newPath . $deptId . ',' . $relativePath;
            $descendant->save();
        }

        return true;
    }

    /**
     * 获取启用的部门列表
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEnabledList()
    {
        return $this->where('status', 1)
            ->where('deleted_at', 0)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * 获取下一个排序值
     * @param array $where 查询条件（支持 parent_id 字段）
     * @return int
     */
    public function getNextSort(array $where = []): int
    {
        $query = $this->where($where);
        $parentId = $where['parent_id'] ?? 0;
        $query->where('parent_id', $parentId);
        $maxSort = $query->max('sort');
        return $maxSort ? $maxSort + 10 : 100;
    }

    /**
     * 获取部门完整名称路径
     * @param int $deptId 部门ID
     * @param string $separator 分隔符
     * @return string
     */
    public function getFullPathName(int $deptId, string $separator = ' / '): string
    {
        $path = [];
        $currentId = $deptId;

        while ($currentId > 0) {
            $dept = $this->find($currentId);
            if (!$dept) {
                break;
            }

            array_unshift($path, $dept->name);
            $currentId = $dept->parent_id;
        }

        return implode($separator, $path);
    }
}
