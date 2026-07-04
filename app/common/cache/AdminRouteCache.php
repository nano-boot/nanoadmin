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
     * 清除指定管理员的路由缓存
     */
    public function clearAdminCache(int $adminId): void
    {
        $this->cache?->delete($this->prefix . 'tree_' . $adminId);
        $this->cache?->delete($this->prefix . 'perm_scope_' . $adminId);
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
