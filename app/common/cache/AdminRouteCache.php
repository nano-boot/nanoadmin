<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\common\cache;

use Illuminate\Support\Facades\DB;
use plugin\nanoadmin\app\common\Cache;
use plugin\nanoadmin\app\model\Admin;
use plugin\nanoadmin\app\model\Menu;
use plugin\nanoadmin\app\model\Permission;
use plugin\nanoadmin\app\model\Role;
use Webman\ThinkCache\Driver;

/**
 * 管理员路由数据缓存
 *
 * 缓存 /sys/menu/route 接口中两个最耗时的数据库查询：
 *  - getAdminMenuTree($adminId)          → Admin 关联 roles.menus + Menu::whereIn
 *  - getAdminButtonPermissionScope($adminId) → Admin 关联 roles.permissions
 *
 * 失效策略（参考 AdminAuthCache 的 MD5 哨兵方案）：
 *  - 联合 sys_menu / sys_role / sys_role_menu / sys_role_permission / sys_permission / sys_admin
 *    6 张表的 (id, updated_at) 签名生成 MD5 指纹。
 *  - 任一张表变更 → 指纹变化 → tag('route') 下所有缓存一次清空。
 *  - 无需在每个 Model 上挂 saved/deleted 事件，避免遗漏。
 *
 * 使用 webman/think-cache（通过 plugin\nanoadmin\app\common\Cache 门面）。
 */
class AdminRouteCache
{
    protected string $prefix = 'route:';
    protected string $tag = 'route';
    protected int $ttl = 3600;

    protected bool $cacheValidChecked = false;

    protected ?Driver $cache = null;

    /**
     * 影响路由缓存的相关表 → 用于构造失效指纹
     */
    private const WATCHED_TABLES = [
        ['menu' => 'sys_menu'],
        ['role' => 'sys_role'],
        ['role_menu' => 'sys_role_menu'],
        ['role_permission' => 'sys_role_permission'],
        ['permission' => 'sys_permission'],
        ['admin' => 'sys_admin'],
        ['admin_role' => 'sys_admin_role'],
    ];

    public function __construct()
    {
        $this->initializeCache();
    }

    /**
     * 初始化缓存驱动
     */
    protected function initializeCache(): void
    {
        $config = config('nanoadmin.cache.route', []);
        if (!($config['enabled'] ?? true)) {
            $this->cache = null;
            return;
        }
        try {
            $store = $config['store'] ?? null;
            $this->prefix = $config['prefix'] ?? $this->prefix;
            $this->ttl = (int) ($config['ttl'] ?? $this->ttl);
            $this->cache = ($store !== null && $store !== '')
                ? Cache::store($store)
                : Cache::store();
        } catch (\Throwable $e) {
            $this->cache = null;
        }
    }

    /**
     * MD5 自动失效：检测相关表是否变更
     */
    protected function ensureCacheValid(): void
    {
        if ($this->cacheValidChecked) {
            return;
        }
        $this->cacheValidChecked = true;

        try {
            $fingerprint = $this->buildFingerprint();
        } catch (\Throwable $e) {
            // DB 异常（未安装、表未迁移）→ 静默跳过
            error_log('[AdminRouteCache] ensureCacheValid skipped: ' . $e->getMessage());
            return;
        }

        try {
            $cachedFingerprint = $this->cache?->get($this->prefix . 'fingerprint');
            if ($cachedFingerprint !== $fingerprint) {
                $this->clearAllCaches();
                $this->cache?->set($this->prefix . 'fingerprint', $fingerprint, 86400 * 365);
            }
        } catch (\Throwable $e) {
            // 缓存不可用 → 不阻断请求
            error_log('[AdminRouteCache] cache fingerprint refresh skipped: ' . $e->getMessage());
        }
    }

    /**
     * 联合多张表的 (id, updated_at) 构造指纹
     */
    private function buildFingerprint(): string
    {
        $parts = [];

        $rows = Menu::query()->select('id', 'updated_at')->get();
        $parts['menu'] = $rows->map(fn ($r) => $r->id . ':' . $r->updated_at)->all();

        $rows = Role::query()->select('id', 'updated_at')->get();
        $parts['role'] = $rows->map(fn ($r) => $r->id . ':' . $r->updated_at)->all();

        $rows = DB::table('sys_role_menu')->select('role_id', 'menu_id', 'updated_at')->get();
        $parts['role_menu'] = $rows->map(fn ($r) => $r->role_id . ':' . $r->menu_id . ':' . $r->updated_at)->all();

        $rows = DB::table('sys_role_permission')->select('role_id', 'permission_id', 'updated_at')->get();
        $parts['role_permission'] = $rows->map(fn ($r) => $r->role_id . ':' . $r->permission_id . ':' . $r->updated_at)->all();

        $rows = Permission::query()->select('id', 'updated_at')->get();
        $parts['permission'] = $rows->map(fn ($r) => $r->id . ':' . $r->updated_at)->all();

        $rows = Admin::query()->select('id', 'updated_at')->get();
        $parts['admin'] = $rows->map(fn ($r) => $r->id . ':' . $r->updated_at)->all();

        $rows = DB::table('sys_admin_role')->select('admin_id', 'role_id', 'updated_at')->get();
        $parts['admin_role'] = $rows->map(fn ($r) => $r->admin_id . ':' . $r->role_id . ':' . $r->updated_at)->all();

        ksort($parts);
        return md5(json_encode($parts, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 清空所有路由缓存（使用 tag 批量失效）
     */
    protected function clearAllCaches(): void
    {
        $this->cache?->tag($this->tag)->clear();
    }

    /**
     * 获取管理员可访问的菜单树（含缓存）
     * @return array 菜单树，根节点是 array，每个节点含 children
     */
    public function getAdminMenuTree(int $adminId): array
    {
        $this->ensureCacheValid();
        $cacheKey = $this->prefix . 'tree_' . $adminId;

        try {
            if ($this->cache) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache read tree skipped: ' . $e->getMessage());
        }

        try {
            $tree = $this->loadAdminMenuTreeFromDb($adminId);
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] loadAdminMenuTree skipped: ' . $e->getMessage());
            return [];
        }

        try {
            if ($this->cache) {
                $this->cache->tag($this->tag)->set($cacheKey, $tree, $this->ttl);
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache write tree skipped: ' . $e->getMessage());
        }

        return $tree;
    }

    /**
     * 从数据库加载管理员菜单树
     */
    private function loadAdminMenuTreeFromDb(int $adminId): array
    {
        $menuModel = new Menu();
        return $menuModel->getAdminMenuTree($adminId);
    }

    /**
     * 获取管理员按钮权限范围（含缓存）
     * @return array{allowAll:bool,codes:array<int,string>}
     */
    public function getAdminButtonPermissionScope(int $adminId): array
    {
        $this->ensureCacheValid();
        $cacheKey = $this->prefix . 'perm_scope_' . $adminId;

        try {
            if ($this->cache) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache read perm_scope skipped: ' . $e->getMessage());
        }

        try {
            $scope = $this->loadAdminButtonPermissionScopeFromDb($adminId);
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] loadAdminButtonPermissionScope skipped: ' . $e->getMessage());
            return ['allowAll' => false, 'codes' => []];
        }

        try {
            if ($this->cache) {
                $this->cache->tag($this->tag)->set($cacheKey, $scope, $this->ttl);
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache write perm_scope skipped: ' . $e->getMessage());
        }

        return $scope;
    }

    /**
     * 从数据库加载管理员按钮权限范围
     */
    private function loadAdminButtonPermissionScopeFromDb(int $adminId): array
    {
        $admin = Admin::with(['roles.permissions'])->find($adminId);
        if (!$admin) {
            return ['allowAll' => false, 'codes' => []];
        }

        $isSuperAdmin = $admin->roles->contains('code', 'R_SUPER');
        if ($isSuperAdmin) {
            return ['allowAll' => true, 'codes' => []];
        }

        $permissions = [];
        foreach ($admin->roles as $role) {
            if ($role->status != 1) {
                continue;
            }
            if (!isset($role->permissions)) {
                continue;
            }
            foreach ($role->permissions as $permission) {
                $code = trim((string)($permission->code ?? ''));
                if ($code !== '') {
                    $permissions[$code] = true;
                }
            }
        }

        return [
            'allowAll' => false,
            'codes' => array_keys($permissions),
        ];
    }

    /**
     * 超级管理员菜单树（专线缓存）
     *
     * 与 getAdminMenuTree($adminId) 的区别：
     *  - 同一个全菜单结果在所有超管之间共享一份（route:tree_full）
     *  - 缓存命中时不再查 DB，无需 loadAdminMenuTreeFromDb 那一组 admin.roles + roles.menus 关联查询
     *  - 失效由 MD5 指纹覆盖（sys_menu 任何变更都会一起清掉）
     *
     * @return array 全菜单树（与 Menu::getTree() 同形态）
     */
    public function getSuperAdminMenuTree(): array
    {
        $this->ensureCacheValid();
        $cacheKey = $this->prefix . 'tree_full';

        try {
            if ($this->cache) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache read tree_full skipped: ' . $e->getMessage());
        }

        try {
            $menuModel = new Menu();
            $tree = $menuModel->getTree();
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] loadFullMenuTreeFromDb skipped: ' . $e->getMessage());
            return [];
        }

        try {
            if ($this->cache) {
                $this->cache->tag($this->tag)->set($cacheKey, $tree, $this->ttl);
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache write tree_full skipped: ' . $e->getMessage());
        }

        return $tree;
    }

    /**
     * 超级管理员按钮权限范围（专线缓存）
     *
     * 直接走常量 {allowAll:true, codes:[]}，但用单独缓存 key 防止
     * "超管降为普通管理员 / 普通升为超管" 时混淆缓存读。
     *
     * - isSuper=true  → 走本方法
     * - isSuper=false → 走 getAdminButtonPermissionScope($adminId)
     *
     * 失效：MD5 指纹（admin_role 变更 → 清空）
     *
     * @return array{allowAll:bool,codes:array<int,string>}
     */
    public function getSuperAdminButtonPermissionScope(): array
    {
        $this->ensureCacheValid();
        $cacheKey = $this->prefix . 'perm_scope_super';

        try {
            if ($this->cache) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache read perm_scope_super skipped: ' . $e->getMessage());
        }

        $scope = ['allowAll' => true, 'codes' => []];

        try {
            if ($this->cache) {
                $this->cache->tag($this->tag)->set($cacheKey, $scope, $this->ttl);
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache write perm_scope_super skipped: ' . $e->getMessage());
        }

        return $scope;
    }

    /**
     * 判断给定管理员是不是超级管理员（持有 R_SUPER 角色）
     *
     * 单独缓存（route:is_super_{adminId}）以便 MenuService 在拿到菜单树之前先分流：
     *  - true  → 走 getSuperAdminMenuTree()，共享全菜单缓存
     *  - false → 走 getAdminMenuTree($adminId)，按 admin 缓存
     *
     * 失效由 MD5 指纹覆盖（admin_role + role 变更 → 清空）
     */
    public function isSuperAdmin(int $adminId): bool
    {
        $this->ensureCacheValid();
        $cacheKey = $this->prefix . 'is_super_' . $adminId;

        try {
            if ($this->cache) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    return (bool) $cached;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache read is_super skipped: ' . $e->getMessage());
        }

        try {
            $admin = Admin::with(['roles'])->find($adminId);
            $isSuper = $admin !== null && $admin->roles->contains('code', 'R_SUPER');
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] isSuperAdmin DB skipped: ' . $e->getMessage());
            return false;
        }

        try {
            if ($this->cache) {
                $this->cache->tag($this->tag)->set($cacheKey, $isSuper, $this->ttl);
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache write is_super skipped: ' . $e->getMessage());
        }

        return $isSuper;
    }

    /**
     * 获取管理员的角色 ID 列表（含缓存）
     *
     * 同一 admin 在角色不变期间只查一次 DB：
     *  - 命中 → 返回 cached array<int>
     *  - 未命中 → 查 admin.roles（只取 id 字段，1 次轻量查询）→ 写入 cache
     *
     * 注意：返回空数组是合法状态（admin 没有任何角色）。cache 用 sentinel 区分
     * "未缓存" 和 "cached as []" — 把空数组包成 ['__empty__' => true] 写入。
     *
     * 失效：MD5 指纹覆盖（admin_role / sys_role 变更 → 清空）。
     *
     * @return array<int> role_id 列表（已去重、按升序）
     */
    public function getAdminRoleIds(int $adminId): array
    {
        $this->ensureCacheValid();
        $cacheKey = $this->prefix . 'admin_roles_' . $adminId;

        try {
            if ($this->cache) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    if (isset($cached['__empty__'])) {
                        return [];
                    }
                    return array_map('intval', (array) $cached);
                }
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache read admin_roles skipped: ' . $e->getMessage());
        }

        try {
            $roleIds = DB::table('sys_admin_role')
                ->where('admin_id', $adminId)
                ->pluck('role_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] getAdminRoleIds DB skipped: ' . $e->getMessage());
            return [];
        }

        try {
            if ($this->cache) {
                $payload = empty($roleIds) ? ['__empty__' => true] : array_values($roleIds);
                $this->cache->tag($this->tag)->set($cacheKey, $payload, $this->ttl);
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache write admin_roles skipped: ' . $e->getMessage());
        }

        return $roleIds;
    }

    /**
     * 按角色 ID 集合获取菜单树（专线缓存）
     *
     * 与 getAdminMenuTree($adminId) 的区别：
     *  - 同一组角色的所有 admin 共享一份菜单树（route:rolemenu_{hash}）
     *  - cache key = md5(implode(',', $sortedRoleIds))，所以"运营 #1" / "运营 #2" /
     *    "运营 #3" 在角色组合完全一致时命中同一份缓存
     *
     * @param array<int> $roleIds 已经查出的角色 ID 列表（按升序）
     * @return array 菜单树，根节点是 array
     */
    public function getMenuTreeByRoleIds(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $this->ensureCacheValid();
        $sortedRoleIds = $roleIds;
        sort($sortedRoleIds);
        $hashKey = md5(implode(',', array_map('intval', $sortedRoleIds)));
        $cacheKey = $this->prefix . 'rolemenu_' . $hashKey;

        try {
            if ($this->cache) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache read rolemenu skipped: ' . $e->getMessage());
        }

        try {
            $menuIds = DB::table('sys_role_menu')
                ->whereIn('role_id', $sortedRoleIds)
                ->pluck('menu_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (empty($menuIds)) {
                return [];
            }

            $menuModel = new Menu();
            $tree = $menuModel->buildTreeFromIds($menuIds);
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] loadRoleMenuTree skipped: ' . $e->getMessage());
            return [];
        }

        try {
            if ($this->cache) {
                $this->cache->tag($this->tag)->set($cacheKey, $tree, $this->ttl);
            }
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] cache write rolemenu skipped: ' . $e->getMessage());
        }

        return $tree;
    }

    /**
     * 清除指定管理员的路由缓存
     */
    public function clearAdminCache(int $adminId): void
    {
        $this->cache?->delete($this->prefix . 'tree_' . $adminId);
        $this->cache?->delete($this->prefix . 'perm_scope_' . $adminId);
        $this->cache?->delete($this->prefix . 'is_super_' . $adminId);
        $this->cache?->delete($this->prefix . 'admin_roles_' . $adminId);
    }

    /**
     * 批量清除多个管理员的路由缓存
     * @param array<int> $adminIds
     */
    public function clearAdminCaches(array $adminIds): void
    {
        if (empty($adminIds) || $this->cache === null) {
            return;
        }
        foreach ($adminIds as $adminId) {
            $this->clearAdminCache((int) $adminId);
        }
    }

    /**
     * 根据角色 ID 集合，清除持有这些角色的所有管理员的路由缓存
     *
     * 典型场景：角色权限/菜单变更、角色删除/批量删除、权限变更（经由 role 间接传播）。
     *
     * @param array<int> $roleIds
     */
    public function clearAdminsByRoleIds(array $roleIds): void
    {
        if (empty($roleIds)) {
            return;
        }
        try {
            $adminIds = DB::table('sys_admin_role')
                ->whereIn('role_id', $roleIds)
                ->pluck('admin_id')
                ->unique()
                ->all();
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] clearAdminsByRoleIds query skipped: ' . $e->getMessage());
            return;
        }

        $this->clearAdminCaches($adminIds);
    }

    /**
     * 根据权限 ID 集合，传播失效：先找持有这些权限的 role，再找这些 role 下的 admin
     *
     * 典型场景：权限的 code / status 变更、权限删除。
     *
     * @param array<int> $permissionIds
     */
    public function clearAdminsByPermissionIds(array $permissionIds): void
    {
        if (empty($permissionIds)) {
            return;
        }
        try {
            $roleIds = DB::table('sys_role_permission')
                ->whereIn('permission_id', $permissionIds)
                ->pluck('role_id')
                ->unique()
                ->all();
        } catch (\Throwable $e) {
            error_log('[AdminRouteCache] clearAdminsByPermissionIds query role skipped: ' . $e->getMessage());
            return;
        }

        $this->clearAdminsByRoleIds($roleIds);
    }

    /**
     * 强制刷新：清空所有路由缓存（下次请求会重建）
     */
    public function clearAll(): void
    {
        $this->clearAllCaches();
        $this->cache?->delete($this->prefix . 'fingerprint');
    }
}
