<?php

namespace plugin\nanoadmin\app\common;

use support\think\Cache as ThinkCache;
use Webman\ThinkCache\Driver;
use Webman\ThinkCache\TagSet;

/**
 * 缓存门面封装
 *
 * 为 IDE 提供完整的代码提示支持
 * 底层委托给 support\think\Cache（webman/think-cache 的 Facade）
 *
 * @method static Driver store(string $name = '')              获取指定缓存驱动实例
 * @method static mixed get(string $key, mixed $default = null) 读取缓存
 * @method static bool set(string $key, mixed $value, mixed $ttl = null) 写入缓存
 * @method static bool delete(string $key)                     删除缓存
 * @method static bool has(string $key)                       判断缓存是否存在
 * @method static TagSet tag(array|string $name)              缓存标签
 * @method static bool clear()                                清空缓存
 * @method static mixed getMultiple(iterable $keys, mixed $default = null) 批量读取
 * @method static bool setMultiple(iterable $values, mixed $ttl = null)     批量写入
 * @method static bool deleteMultiple(iterable $keys)          批量删除
 */
class Cache
{
    /**
     * 获取默认缓存驱动
     *
     * @return Driver
     */
    public static function driver(): Driver
    {
        return ThinkCache::store();
    }

    /**
     * 获取指定 store 的缓存驱动
     *
     * @param string $name store 名称
     * @return Driver
     */
    public static function store(string $name = ''): Driver
    {
        return ThinkCache::store($name);
    }

    /**
     * 读取缓存
     *
     * @param string $key     缓存键名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return ThinkCache::get($key, $default);
    }

    /**
     * 写入缓存
     *
     * @param string $key   缓存键名
     * @param mixed  $value 缓存值
     * @param mixed  $ttl   过期时间（秒），0 表示永久
     * @return bool
     */
    public static function set(string $key, mixed $value, mixed $ttl = null): bool
    {
        return ThinkCache::set($key, $value, $ttl);
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public static function delete(string $key): bool
    {
        return ThinkCache::delete($key);
    }

    /**
     * 判断缓存是否存在
     *
     * @param string $key 缓存键名
     * @return bool
     */
    public static function has(string $key): bool
    {
        return ThinkCache::has($key);
    }

    /**
     * 缓存标签
     *
     * @param array|string $name 标签名
     * @return TagSet
     */
    public static function tag(array|string $name): TagSet
    {
        return ThinkCache::tag($name);
    }

    /**
     * 清空缓存
     *
     * @return bool
     */
    public static function clear(): bool
    {
        return ThinkCache::clear();
    }

    /**
     * 批量读取缓存
     *
     * @param iterable $keys    缓存键名列表
     * @param mixed    $default 默认值
     * @return iterable
     */
    public static function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return ThinkCache::getMultiple($keys, $default);
    }

    /**
     * 批量写入缓存
     *
     * @param iterable $values 缓存数据 [key => value]
     * @param mixed   $ttl    过期时间
     * @return bool
     */
    public static function setMultiple(iterable $values, mixed $ttl = null): bool
    {
        return ThinkCache::setMultiple($values, $ttl);
    }

    /**
     * 批量删除缓存
     *
     * @param iterable $keys 缓存键名列表
     * @return bool
     */
    public static function deleteMultiple(iterable $keys): bool
    {
        return ThinkCache::deleteMultiple($keys);
    }

    /**
     * 读取后删除（弹栈）
     *
     * @param string $key 缓存键名
     * @return mixed
     */
    public static function pull(string $key): mixed
    {
        return ThinkCache::pull($key);
    }

    /**
     * 如果不存在则写入缓存
     *
     * @param string        $key    缓存键名
     * @param mixed|callable $value 缓存值或闭包
     * @param mixed         $ttl    过期时间
     * @return mixed
     */
    public static function remember(string $key, mixed $value, mixed $ttl = null): mixed
    {
        return ThinkCache::remember($key, $value, $ttl);
    }

    /**
     * 追加（数组）缓存
     *
     * @param string $key   缓存键名
     * @param mixed  $value 追加的值
     * @return void
     */
    public static function push(string $key, mixed $value): void
    {
        ThinkCache::push($key, $value);
    }

    /**
     * 追加 TagSet 数据
     *
     * @param string $key   缓存键名
     * @param mixed  $value 追加的值
     * @return void
     */
    public static function append(string $key, mixed $value): void
    {
        ThinkCache::append($key, $value);
    }
}
