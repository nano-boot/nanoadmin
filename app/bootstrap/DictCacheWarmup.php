<?php

namespace plugin\theadmin\app\bootstrap;

use Webman\Bootstrap;
use Workerman\Worker;

/**
 * 字典缓存预热引导类
 * 服务启动时将常用字典数据加载到缓存
 */
class DictCacheWarmup implements Bootstrap
{
    /**
     * 缓存实例
     */
    private static $cache = null;

    /**
     * 缓存配置
     */
    private static array $cacheConfig = [];

    /**
     * 所有进程只预热一次
     */
    private static bool $warmedUp = false;

    /**
     * 启动引导
     * @param Worker|null $worker
     */
    public static function start(?Worker $worker): void
    {
        if (self::$warmedUp) {
            return;
        }

        // 只在主进程启动时预热
        if ($worker === null || $worker->id === 0) {
            self::loadCacheConfig();
            self::initializeCache();
            self::doWarmup();
            self::$warmedUp = true;
        }
    }

    /**
     * Worker 进程启动时执行
     * @param Worker $worker
     */
    public static function onWorkerStart(Worker $worker): void
    {
        // 每个 worker 启动时初始化缓存连接
        self::loadCacheConfig();
        self::initializeCache();
    }

    /**
     * 加载缓存配置
     */
    private static function loadCacheConfig(): void
    {
        $configFile = base_path() . '/plugin/theadmin/config/cache.php';

        if (file_exists($configFile)) {
            self::$cacheConfig = require $configFile;
        } else {
            self::$cacheConfig = [
                'type' => 'file',
                'dict' => [
                    'enabled' => true,
                    'prefix' => 'dict:',
                    'ttl' => 0,
                    'warmup_enabled' => true,
                    'warmup_codes' => [],
                ],
                'redis' => [
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'password' => '',
                    'database' => 1,
                    'prefix' => 'theadmin_cache:',
                ],
                'file' => [
                    'path' => runtime_path() . '/cache/theadmin/',
                    'prefix' => 'theadmin_cache_',
                ],
            ];
        }
    }

    /**
     * 初始化缓存连接
     */
    private static function initializeCache(): void
    {
        $dictConfig = self::$cacheConfig['dict'] ?? [];

        if (!($dictConfig['enabled'] ?? true)) {
            self::$cache = null;
            return;
        }

        try {
            $cacheType = self::$cacheConfig['type'] ?? 'file';

            if ($cacheType === 'redis' && class_exists('\Redis')) {
                $redisConfig = self::$cacheConfig['redis'] ?? [];
                self::$cache = new \Redis();

                $host = $redisConfig['host'] ?? '127.0.0.1';
                $port = $redisConfig['port'] ?? 6379;
                $timeout = $redisConfig['timeout'] ?? 2;

                if (self::$cache->connect($host, $port, $timeout)) {
                    if (!empty($redisConfig['password'])) {
                        self::$cache->auth($redisConfig['password']);
                    }
                    $database = $redisConfig['database'] ?? 1;
                    self::$cache->select($database);
                } else {
                    self::$cache = null;
                }
            } else {
                self::$cache = null;
            }
        } catch (\Exception $e) {
            self::$cache = null;
        }
    }

    /**
     * 执行缓存预热
     */
    private static function doWarmup(): void
    {
        $dictConfig = self::$cacheConfig['dict'] ?? [];

        if (!($dictConfig['warmup_enabled'] ?? true)) {
            return;
        }

        try {
            // 使用 ModelFactory 获取模型实例
            $dictTypeModel = self::getModel('dict_type');
            $dictDataModel = self::getModel('dict_data');

            if (!$dictTypeModel) {
                return;
            }

            // 预热所有字典类型
            $types = $dictTypeModel->where('status', 1)->where('deleted', 0)->get()->toArray();

            foreach ($types as $type) {
                self::warmupType($type, $dictDataModel);
            }
        } catch (\Exception $e) {
            // 静默降级，不影响服务启动
        }
    }

    /**
     * 预热单个字典类型及其数据
     */
    private static function warmupType(array $type, $dictDataModel): void
    {
        $prefix = self::getCachePrefix();

        // 缓存字典类型（按 ID）
        $typeByIdKey = $prefix . 'type:id:' . $type['id'];
        self::setCache($typeByIdKey, $type);

        // 缓存字典类型（按编码）
        $typeByCodeKey = $prefix . 'type:code:' . $type['code'];
        self::setCache($typeByCodeKey, $type);

        // 缓存字典类型列表
        $allTypesKey = $prefix . 'type:all';
        $allTypes = self::getCache($allTypesKey) ?? [];
        $allTypes[$type['id']] = $type;
        self::setCache($allTypesKey, $allTypes);

        // 预热字典数据
        if ($dictDataModel) {
            $dataList = $dictDataModel
                ->where('dict_type_id', $type['id'])
                ->where('status', 1)
                ->where('deleted', 0)
                ->orderBy('sort', 'asc')
                ->get()
                ->toArray();

            $dataByCodeKey = $prefix . 'data:code:' . $type['code'];
            self::setCache($dataByCodeKey, $dataList);
        }
    }

    /**
     * 获取缓存键前缀
     */
    private static function getCachePrefix(): string
    {
        $redisPrefix = self::$cacheConfig['redis']['prefix'] ?? 'theadmin_cache:';
        $dictPrefix = self::$cacheConfig['dict']['prefix'] ?? 'dict:';
        return $redisPrefix . $dictPrefix;
    }

    /**
     * 获取模型实例
     */
    private static function getModel(string $name)
    {
        $factoryClass = 'plugin\theadmin\app\model\ModelFactory';

        if (!class_exists($factoryClass)) {
            return null;
        }

        if (!method_exists($factoryClass, $name)) {
            return null;
        }

        return $factoryClass::$name();
    }

    /**
     * 设置缓存
     */
    private static function setCache(string $key, $data): bool
    {
        if (self::$cache === null) {
            return self::setFileCache($key, $data);
        }

        try {
            $ttl = self::$cacheConfig['dict']['ttl'] ?? 0;
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

            // TTL 为 0 表示永久缓存，Redis setex 第二个参数为 0 时会立即过期
            // 改用 set 方法设置字符串，TTL 为 0 时不设置过期时间
            if ($ttl === 0) {
                return self::$cache->set($key, $jsonData);
            }

            return self::$cache->setex($key, $ttl, $jsonData);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取缓存
     */
    private static function getCache(string $key)
    {
        if (self::$cache === null) {
            return self::getFileCache($key);
        }

        try {
            $data = self::$cache->get($key);
            if ($data === false) {
                return null;
            }
            return json_decode($data, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 设置文件缓存
     */
    private static function setFileCache(string $key, $data): bool
    {
        $fileConfig = self::$cacheConfig['file'] ?? [];
        $cachePath = $fileConfig['path'] ?? runtime_path() . '/cache/theadmin/';
        $prefix = $fileConfig['prefix'] ?? 'theadmin_cache_';
        $cacheFile = $cachePath . $prefix . md5($key) . '.json';

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $cacheData = [
            'expire_time' => 0, // 永久缓存
            'data' => $data,
        ];

        return file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE)) !== false;
    }

    /**
     * 获取文件缓存
     */
    private static function getFileCache(string $key)
    {
        $fileConfig = self::$cacheConfig['file'] ?? [];
        $cachePath = $fileConfig['path'] ?? runtime_path() . '/cache/theadmin/';
        $prefix = $fileConfig['prefix'] ?? 'theadmin_cache_';
        $cacheFile = $cachePath . $prefix . md5($key) . '.json';

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheData = file_get_contents($cacheFile);
        if ($cacheData === false) {
            return null;
        }

        $cache = json_decode($cacheData, true);
        if (!$cache || !isset($cache['data'])) {
            return null;
        }

        return $cache['data'];
    }
}
