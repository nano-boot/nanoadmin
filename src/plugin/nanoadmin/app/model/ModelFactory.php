<?php

namespace plugin\nanoadmin\app\model;

/**
 * 模型工厂类
 * 用于统一创建和管理模型实例
 */
class ModelFactory
{
    /**
     * 模型实例缓存
     * @var array
     */
    private static array $instances = [];

    /**
     * 获取Admin模型实例
     * @return Admin
     */
    public static function admin(): Admin
    {
        return self::getInstance(Admin::class);
    }

    /**
     * 获取Role模型实例
     * @return Role
     */
    public static function role(): Role
    {
        return self::getInstance(Role::class);
    }

    /**
     * 获取Permission模型实例
     * @return Permission
     */
    public static function permission(): Permission
    {
        return self::getInstance(Permission::class);
    }

    /**
     * 获取Menu模型实例
     * @return Menu
     */
    public static function menu(): Menu
    {
        return self::getInstance(Menu::class);
    }

    /**
     * 获取File模型实例
     * @return File
     */
    public static function file(): File
    {
        return self::getInstance(File::class);
    }

    /**
     * 获取字典类型模型实例
     * @return DictType
     */
    public static function dict_type(): DictType
    {
        return self::getInstance(DictType::class);
    }

    /**
     * 获取字典数据模型实例
     * @return DictData
     */
    public static function dict_data(): DictData
    {
        return self::getInstance(DictData::class);
    }

    /**
     * 获取登录日志模型实例
     * @return LogLogin
     */
    public static function log_login(): LogLogin
    {
        return self::getInstance(LogLogin::class);
    }

    /**
     * 获取操作日志模型实例
     * @return LogOperation
     */
    public static function log_operation(): LogOperation
    {
        return self::getInstance(LogOperation::class);
    }

    /**
     * 获取模型实例
     * @param string $className 模型类名
     * @return mixed
     */
    private static function getInstance(string $className): mixed
    {
        if (!isset(self::$instances[$className])) {
            self::$instances[$className] = new $className();
        }
        
        return self::$instances[$className];
    }

    /**
     * 清除模型实例缓存
     * @param string|null $className 模型类名，为空则清除所有
     * @return void
     */
    public static function clearCache(string $className = null): void
    {
        if ($className) {
            unset(self::$instances[$className]);
        } else {
            self::$instances = [];
        }
    }
}