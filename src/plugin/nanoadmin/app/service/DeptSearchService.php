<?php

namespace plugin\nanoadmin\app\service;

use Illuminate\Support\Collection;
use plugin\nanoadmin\app\model\Dept;

/**
 * 部门搜索服务
 *
 * 与 Menu 树搜索场景对称：
 *  - 命中行通过 Dept::$searchKeywordFields / Dept::$searchLikeFields /
 *    Dept::$searchEqualFields 在 baseModel::handleSearch() 里统一组装 SQL
 *  - 命中行需要保持原始树形结构（命中行挂到真实父部门下），所以还要把
 *    命中行的祖先部门也加载进来
 *
 * 性能差异（相对 MenuSearchService）：
 *  Dept 表有物化路径 `path`（形如 `,0,1,5,`，记录 *父级链* 而非包含自身 id），
 *  祖先 ID 直接从字符串里切出来后一次 whereIn 即可加载完，比 MenuSearchService
 *  按层 BFS 多次 SELECT 还要快，绝大多数场景只需 2 次查询（命中 + 祖先兜底）。
 */
class DeptSearchService
{
    /**
     * 部门模型实例
     * @var Dept
     */
    private Dept $deptModel;

    public function __construct()
    {
        $this->deptModel = new Dept();
    }

    /**
     * 高级搜索（多条件组合，保持树形结构）
     *
     * @param array $searchParams 搜索参数，字段由 DeptValidator::tree + Dept::handleSearch 约束
     * @return array 树形结构（顶层从 parent_id=0 开始）
     */
    public function advancedSearch(array $searchParams): array
    {
        // 1. 用 baseModel::handleSearch 跑命中行（status / keyword / name / code 都覆盖）
        $query = $this->deptModel->handleSearch($this->deptModel->newQuery(), $searchParams);
        $matchedDepts = $query->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // 2. 加载所有祖先（基于 dept.path 物化路径），保证返回的树能展示匹配部门的真实层级
        $ancestorDepts = $this->loadAncestors($matchedDepts);

        // 3. 合并去重，再按 parent_id 装配出树
        return $this->buildTreeFromCollection($matchedDepts->merge($ancestorDepts));
    }

    /**
     * 从匹配行出发，通过解析 `path` 一次加载所有祖先
     *
     * 【性能优化】dept.path 形如 `,0,1,5,`（见 Dept::calculatePath，记录的
     * 是父级链而非自身 id），祖先 ID 直接从字符串里切出来后一次 whereIn 即
     * 可加载完，比 MenuSearchService 按层 BFS 多次 SELECT 还要快，绝大多数
     * 场景只需 2 次查询（命中 + 祖先兜底）。
     *
     * @param Collection $matchedDepts 命中行 Eloquent 集合
     * @return Collection 祖先 Eloquent 集合（不含命中行）
     */
    private function loadAncestors(Collection $matchedDepts): Collection
    {
        if ($matchedDepts->isEmpty()) {
            return collect();
        }

        $ancestorIds = [];

        foreach ($matchedDepts as $dept) {
            $path = (string)($dept->path ?? '');
            if ($path === '') {
                continue;
            }

            // path 形如 ',0,1,5,' → 切分后就是祖先 ID 序列 [0, 1, 5]
            //（注意 dept.path 不含自身 id，详见 Dept::calculatePath）
            $parts = array_values(array_filter(explode(',', $path), static fn($s) => $s !== ''));
            if (empty($parts)) {
                continue;
            }

            foreach ($parts as $pid) {
                $pid = (int)$pid;
                // 0 是约定的"虚拟根"，不需要加载
                if ($pid > 0) {
                    $ancestorIds[$pid] = true;
                }
            }
        }

        if (empty($ancestorIds)) {
            return collect();
        }

        $matchedIds = $matchedDepts->pluck('id')->all();
        $ancestorIds = array_keys($ancestorIds);

        // 排除已经在命中行里的（避免重复加载）
        $ancestorIds = array_values(array_diff($ancestorIds, $matchedIds));
        if (empty($ancestorIds)) {
            return collect();
        }

        return $this->deptModel->whereIn('id', $ancestorIds)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * 从 Eloquent 集合构建树（顶层从 parent_id=0 开始）
     *
     * @param Collection $depts 命中 + 祖先 的并集
     * @return array
     */
    private function buildTreeFromCollection(Collection $depts): array
    {
        $deptMap = [];
        foreach ($depts as $dept) {
            $deptArray = $dept->toArray();
            $deptArray['children'] = [];
            $deptMap[$dept->id] = $deptArray;
        }

        $tree = [];
        foreach ($deptMap as $dept) {
            if (($dept['parent_id'] ?? 0) == 0) {
                $tree[] = &$deptMap[$dept['id']];
            } elseif (isset($deptMap[$dept['parent_id']])) {
                $deptMap[$dept['parent_id']]['children'][] = &$deptMap[$dept['id']];
            } else {
                // 祖先缺失（理论上不会发生，因为我们已经把祖先全部加载进来），
                // 兜底挂到顶层，避免命中行沦落为顶层
                $tree[] = &$deptMap[$dept['id']];
            }
        }
        unset($dept);

        return $tree;
    }
}
