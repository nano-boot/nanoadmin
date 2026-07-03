<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\common\cache;

use plugin\nanoadmin\app\model\Admin;
use plugin\nanoadmin\app\model\Permission;
use support\Redis;

/**
 * 管理员权限缓存
 *
 * 设计要点：
 * - MD5 自动失效：权限/角色表变更时，所有缓存自动清空
 * - 单个用户权限列表缓存
 * - 底层使用 webman/redis（项目已有依赖），不走 webman/think-cache
 *
 * LikeAdmin 风格：保证权限表一有变化，下次请求立即失效所有缓存
 */
class AdminAuthCache
{
    protected string $prefix = 'nanoadmin:auth:';
    protected int $ttl = 3600;

    // 是否已经做过 MD5 失效检测
    protected bool $cacheValidChecked = false;

    /**
     * MD5 自动失效：检测权限表是否变更
     */
    protected function ensureCacheValid(): void
    {
        if ($this->cacheValidChecked) {
            return;
        }
        $this->cacheValidChecked = true;

        try {
            $allPermissions = $this->loadAllPermissions();
        } catch (\Throwable $e) {
            error_log('[AdminAuthCache] ensureCacheValid skipped: ' . $e->getMessage());
            return;
        }

        try {
            sort($allPermissions);
            $currentMd5 = md5(json_encode($allPermissions));

            $cachedMd5 = Redis::get($this->prefix . 'md5');

            if ($cachedMd5 !== $currentMd5) {
                $this->clearAllCaches();
                Redis::setEx($this->prefix . 'md5', 86400 * 365, $currentMd5);
            }
        } catch (\Throwable $e) {
            // Redis 不可用 / 安装阶段尚未配置 Redis → 不阻断请求
            error_log('[AdminAuthCache] cache md5 refresh skipped: ' . $e->getMessage());
        }
    }

    /**
     * 加载所有启用的权限码
     * @return array<string>
     */
    protected function loadAllPermissions(): array
    {
        return Permission::where('status', 1)
            ->where('deleted', false)
            ->whereNotNull('code')
            ->where('code', '<>', '')
            ->pluck('code')
            ->toArray();
    }

    // 清空所有权限缓存
    protected function clearAllCaches(): void
    {
        $pattern = $this->prefix . 'admin_perms_*';
        $cursor = null;
        do {
            [$cursor, $keys] = Redis::scan($cursor ?? 0, ['match' => $pattern, 'count' => 100]);
            if (!empty($keys)) {
                Redis::del(...$keys);
            }
        } while ((int) $cursor !== 0);
    }

    /**
     * 获取用户的权限列表（含缓存）
     * @param int $adminId 管理员ID
     * @return array<string> 权限码数组
     */
    public function getAdminPermissions(int $adminId): array
    {
        $this->ensureCacheValid();

        $cacheKey = $this->prefix . 'admin_perms_' . $adminId;

        try {
            $cached = Redis::get($cacheKey);
            if ($cached !== null && $cached !== false) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable $e) {
            // Redis 不可用 / 未安装阶段 → 继续走 DB 兜底
            error_log('[AdminAuthCache] redis read skipped: ' . $e->getMessage());
        }

        try {
            $permissions = $this->loadAdminPermissions($adminId);
        } catch (\Throwable $e) {
            // DB 未配置 / 表不存在 → 降级为空权限（由 fail-closed 中间件拒绝访问，提示用户先完成安装）
            error_log('[AdminAuthCache] loadAdminPermissions skipped: ' . $e->getMessage());
            return [];
        }

        try {
            Redis::setEx($cacheKey, $this->ttl, json_encode($permissions));
        } catch (\Throwable $e) {
            // 写缓存失败不影响返回
            error_log('[AdminAuthCache] redis write skipped: ' . $e->getMessage());
        }

        return $permissions;
    }

    /**
     * 从数据库加载用户权限
     * @param int $adminId
     * @return array<string>
     */
    protected function loadAdminPermissions(int $adminId): array
    {
        $admin = Admin::with(['roles.permissions' => function ($query) {
            $query->where('status', 1)
                  ->where('deleted', false)
                  ->whereNotNull('code')
                  ->where('code', '<>', '');
        }])->find($adminId);

        if (!$admin) {
            return [];
        }

        $permissions = [];
        foreach ($admin->roles as $role) {
            if ($role->status != 1) {
                continue;
            }
            foreach ($role->permissions as $permission) {
                if (!empty($permission->code)) {
                    $permissions[$permission->code] = true;
                }
            }
        }

        return array_keys($permissions);
    }

    /**
     * 清除指定管理员的权限缓存
     */
    public function clearAdminCache(int $adminId): void
    {
        Redis::del($this->prefix . 'admin_perms_' . $adminId);
    }

    /**
     * 强制刷新：清空所有权限缓存
     */
    public function clearAll(): void
    {
        $this->clearAllCaches();
        Redis::del($this->prefix . 'md5');
    }
}