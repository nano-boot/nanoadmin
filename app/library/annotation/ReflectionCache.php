<?php

declare(strict_types=1);

namespace plugin\nanoadmin\app\library\annotation;

use ReflectionClass;
use ReflectionMethod;
use plugin\nanoadmin\app\attribute\Permission;
use plugin\nanoadmin\app\attribute\AllowAnonymous;

/**
 * 反射缓存工具（Phase 2 新增）
 *
 * 提供以下静态方法，供中间件读取注解：
 *  - getAllowAnonymous(controller, action)   → 读取 #[AllowAnonymous] 注解
 *  - getPermissionAttributes(controller, action) → 读取方法级 #[Permission] 注解列表
 *  - getClassAttributes(controller)         → 读取类级 #[Permission] 注解列表
 *  - getNoNeedLogin(controller)             → 读取 $noNeedLogin 属性（saiadmin 兼容）
 *  - resolvePermissions(controller, action) → 方法级 + 类级合并的权限码列表
 *  - clear()                                → 一键清空所有反射缓存（部署时调用）
 *
 * 设计要点（来源：authorization-refactoring-plan.md §2.9）：
 *  1. 按 controller/action 分 key 缓存，粒度细、命中率高
 *  2. 负命中也缓存（用 ['__null__'] 哨兵值区分"未缓存"和"缓存为 null"）
 *  3. tag-based 失效：所有反射缓存共用一个 tag，Cache::tag($tag)->clear() 一次清空
 *  4. 1 年 TTL：依赖部署时清缓存，平时不失效
 *  5. 沿继承链追溯：子类继承父类 controller 时注解不丢
 *  6. 环境隔离：tag / key 加 env 前缀，避免 dev/staging/prod 共用 Redis 串扰
 *
 * 关键差异（与 saiadmin 对比）：
 *  - Cache facade 用 support\think\Cache（webman/think-cache 提供的 Facade，
 *    计划文档原写 think\facade\Cache 是 topthink/think-cache 的路径，
 *    与本仓库实际 composer.json 中的 webman/think-cache ^2.1 不一致 → 纠正）
 *  - 配置文件 plugin.nanoadmin.annotation（saiadmin 用 plugin.saiadmin.saithink.reflection_cache）
 *  - 加环境前缀（避免多环境串扰，saiadmin 全局 tag）
 *  - 处理继承链（saiadmin 不处理）
 *  - 支持 AllowAnonymous 双参数（saiadmin 只支持 $noNeedLogin）
 *  - 缓存字段名对齐 madong（requireToken / requirePermission）
 */
class ReflectionCache
{
    /**
     * 负命中哨兵值（避免数组型与 null 冲突）
     */
    private const NULL_SENTINEL = ['__null__'];

    /**
     * 读取缓存配置（运行时拼接 env 前缀，避免修改配置）
     *
     * @return array
     */
    public static function config(): array
    {
        $env = function_exists('config') ? (string) config('app.env', 'production') : 'production';
        $defaults = [
            'tag'           => "nanoadmin:reflection:{$env}",
            'expire'        => 60 * 60 * 24 * 365, // 1 年
            'permission'    => "nanoadmin:reflection:{$env}:permission_",
            'class'         => "nanoadmin:reflection:{$env}:class_",
            'anonymous'     => "nanoadmin:reflection:{$env}:anonymous_",
            'no_need_login' => "nanoadmin:reflection:{$env}:no_need_login_",
        ];

        if (!function_exists('config')) {
            return $defaults;
        }

        return array_merge($defaults, (array) config('plugin.nanoadmin.annotation', []));
    }

    /**
     * 获取缓存 Facade 根类。
     *
     * nanoadmin 仓库 composer.json 实际依赖是 webman/think-cache ^2.1，
     * 其 Facade 路径是 support\think\Cache。
     * 计划文档里写的 think\facade\Cache 是 topthink/think-cache 的路径，
     * 不在本仓库的依赖图里。这里做硬编码取已确认存在的路径。
     */
    private static function cacheFacade(): string
    {
        return 'support\\think\\Cache';
    }

    /**
     * 读取缓存（封装 Cache::get，便于测试时替换）
     */
    private static function cacheGet(string $key)
    {
        try {
            $facade = static::cacheFacade();
            return $facade::get($key);
        } catch (\Throwable $e) {
            // 缓存不可用（未初始化等场景）时回退到反射本身
            return null;
        }
    }

    /**
     * 写入缓存（带 tag）
     */
    private static function cacheSet(string $key, $value, int $expire, string $tag): void
    {
        try {
            $facade = static::cacheFacade();
            $facade::tag($tag)->set($key, $value, $expire);
        } catch (\Throwable $e) {
            // 缓存不可用时静默忽略（不影响主流程）
        }
    }

    /**
     * 获取方法级 #[AllowAnonymous] 注解（沿继承链追溯）
     *
     * 返回结构：['requireToken' => bool, 'requirePermission' => bool, 'description' => ?string]
     * 命中规则：
     *  - 找到：返回关联数组（带缓存）
     *  - 没找到：返回 null（用 ['__null__'] 哨兵缓存负命中，避免每次反射）
     *
     * @param string $controller 完全限定类名
     * @param string $action     方法名
     * @return array|null
     */
    public static function getAllowAnonymous(string $controller, string $action): ?array
    {
        $cfg = static::config();
        $key = $cfg['anonymous'] . md5($controller . '::' . $action);

        $data = static::cacheGet($key);
        if ($data !== null) {
            if ($data === self::NULL_SENTINEL) {
                return null;
            }
            return is_array($data) ? $data : null;
        }

        $result = null;
        $current = $controller;
        while ($current && class_exists($current)) {
            if (method_exists($current, $action)) {
                try {
                    $refMethod = new ReflectionMethod($current, $action);
                    $attributes = $refMethod->getAttributes(AllowAnonymous::class);
                    if (!empty($attributes)) {
                        $instance = $attributes[0]->newInstance();
                        $result = [
                            'requireToken'      => (bool) $instance->requireToken,
                            'requirePermission' => (bool) $instance->requirePermission,
                            'description'       => $instance->description,
                        ];
                    }
                } catch (\Throwable $e) {
                    // 反射失败（编译错误等），返回 null
                    $result = null;
                }
                // 方法定义在哪就用哪里的注解，不向父类追溯
                break;
            }
            $current = get_parent_class($current);
        }

        // 负命中缓存：用 ['__null__'] 哨兵值
        $cached = $result === null ? self::NULL_SENTINEL : $result;
        static::cacheSet($key, $cached, (int) $cfg['expire'], (string) $cfg['tag']);

        return $result;
    }

    /**
     * 获取方法级 #[Permission] 注解列表（沿继承链追溯）
     *
     * @return array<int, array{title:string,code:string,module:string,action:string,log:bool}>
     */
    public static function getPermissionAttributes(string $controller, string $action): array
    {
        $cfg = static::config();
        $key = $cfg['permission'] . md5($controller . '::' . $action);

        $data = static::cacheGet($key);
        if ($data !== null) {
            return is_array($data) ? $data : [];
        }

        $result = [];
        $current = $controller;
        while ($current && class_exists($current)) {
            if (method_exists($current, $action)) {
                try {
                    $refMethod = new ReflectionMethod($current, $action);
                    $attributes = $refMethod->getAttributes(Permission::class);
                    foreach ($attributes as $attr) {
                        $instance = $attr->newInstance();
                        $result[] = [
                            'title'  => (string) $instance->title,
                            'code'   => (string) $instance->code,
                            'module' => (string) $instance->module,
                            'action' => (string) $instance->action,
                            'log'    => (bool) $instance->log,
                        ];
                    }
                } catch (\Throwable $e) {
                    // 反射失败时返回空数组
                    $result = [];
                }
                // 方法定义在哪就用哪里的注解，不向父类追溯
                break;
            }
            $current = get_parent_class($current);
        }

        static::cacheSet($key, $result, (int) $cfg['expire'], (string) $cfg['tag']);
        return $result;
    }

    /**
     * 获取类级 #[Permission] 注解列表（沿继承链追溯到定义点）
     *
     * @return array<int, array{title:string,code:string,module:string,action:string,log:bool}>
     */
    public static function getClassAttributes(string $controller): array
    {
        $cfg = static::config();
        $key = $cfg['class'] . md5($controller);

        $data = static::cacheGet($key);
        if ($data !== null) {
            return is_array($data) ? $data : [];
        }

        $result = [];
        if (class_exists($controller)) {
            $current = $controller;
            while ($current && class_exists($current)) {
                try {
                    $ref = new ReflectionClass($current);
                    $attributes = $ref->getAttributes(Permission::class);
                    foreach ($attributes as $attr) {
                        $instance = $attr->newInstance();
                        $result[] = [
                            'title'  => (string) $instance->title,
                            'code'   => (string) $instance->code,
                            'module' => (string) $instance->module,
                            'action' => (string) $instance->action,
                            'log'    => (bool) $instance->log,
                        ];
                    }
                } catch (\Throwable $e) {
                    $result = [];
                }
                // 类级只取第一个有注解的定义点
                if (!empty($result)) {
                    break;
                }
                $current = get_parent_class($current);
            }
        }

        static::cacheSet($key, $result, (int) $cfg['expire'], (string) $cfg['tag']);
        return $result;
    }

    /**
     * 获取控制器 $noNeedLogin 属性（兼容 saiadmin，Phase 2 过渡期）
     *
     * 用途：在 AllowAnonymous 注解落地前，老代码用 $noNeedLogin 属性仍可工作
     * Phase 3 后可移除（所有 controller 改为 AllowAnonymous 注解）
     *
     * @param string $controller
     * @return array<int, string> 方法名列表
     */
    public static function getNoNeedLogin(string $controller): array
    {
        $cfg = static::config();
        $key = $cfg['no_need_login'] . md5($controller);

        $data = static::cacheGet($key);
        if ($data !== null) {
            return is_array($data) ? $data : [];
        }

        $result = [];
        if (class_exists($controller)) {
            try {
                $ref = new ReflectionClass($controller);
                $data = $ref->getDefaultProperties()['noNeedLogin'] ?? [];
                $result = is_array($data) ? array_values($data) : [];
            } catch (\Throwable $e) {
                $result = [];
            }
        }

        static::cacheSet($key, $result, (int) $cfg['expire'], (string) $cfg['tag']);
        return $result;
    }

    /**
     * 解析某 controller.action 的完整权限码列表（方法级优先，类级兜底）
     *
     * @return array<int, array{title:string,code:string,module:string,action:string,log:bool}>
     */
    public static function resolvePermissions(string $controller, string $action): array
    {
        $methodAttrs = static::getPermissionAttributes($controller, $action);
        if (!empty($methodAttrs)) {
            return $methodAttrs;
        }
        return static::getClassAttributes($controller);
    }

    /**
     * 清理所有反射缓存（部署时调用）
     *
     * 通过 tag 一次清空所有 nanoadmin:reflection:* 的 key。
     *
     * @return bool
     */
    public static function clear(): bool
    {
        $cfg = static::config();
        try {
            $facade = static::cacheFacade();
            return $facade::tag((string) $cfg['tag'])->clear();
        } catch (\Throwable $e) {
            return false;
        }
    }
}