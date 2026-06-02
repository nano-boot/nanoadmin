<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\model\DictData;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;

/**
 * 字典数据服务类
 */
class DictDataService extends BaseService
{
    /**
     * 缓存配置
     * @var array
     */
    private array $cacheConfig = [];

    /**
     * 缓存实例
     * @var \Redis|null
     */
    private $cache = null;

    /**
     * 构造函数
     * @param DictData $model 字典数据模型实例
     */
    public function __construct(DictData $model)
    {
        parent::__construct($model);
        $this->loadCacheConfig();
        $this->initializeCache();
    }

    /**
     * 加载缓存配置
     */
    private function loadCacheConfig(): void
    {
        $configFile = base_path() . '/plugin/theadmin/config/cache.php';

        if (file_exists($configFile)) {
            $this->cacheConfig = require $configFile;
        } else {
            $this->cacheConfig = [
                'dict' => ['enabled' => false],
            ];
        }
    }

    /**
     * 初始化缓存连接
     */
    private function initializeCache(): void
    {
        $dictConfig = $this->cacheConfig['dict'] ?? [];

        if (!($dictConfig['enabled'] ?? true)) {
            $this->cache = null;
            return;
        }

        try {
            $cacheType = $this->cacheConfig['type'] ?? 'file';

            if ($cacheType === 'redis' && class_exists('\Redis')) {
                $redisConfig = $this->cacheConfig['redis'] ?? [];
                $this->cache = new \Redis();

                $host = $redisConfig['host'] ?? '127.0.0.1';
                $port = $redisConfig['port'] ?? 6379;
                $timeout = $redisConfig['timeout'] ?? 2;

                if ($this->cache->connect($host, $port, $timeout)) {
                    if (!empty($redisConfig['password'])) {
                        $this->cache->auth($redisConfig['password']);
                    }
                    $database = $redisConfig['database'] ?? 1;
                    $this->cache->select($database);
                } else {
                    $this->cache = null;
                }
            } else {
                $this->cache = null;
            }
        } catch (\Exception $e) {
            $this->cache = null;
        }
    }

    /**
     * 获取缓存键前缀
     */
    private function getCachePrefix(): string
    {
        $redisPrefix = $this->cacheConfig['redis']['prefix'] ?? 'theadmin:';
        $dictPrefix = $this->cacheConfig['dict']['prefix'] ?? 'dict:';
        return $redisPrefix . $dictPrefix;
    }

    /**
     * 获取记录不存在时的错误代码
     * @return Code
     */
    protected function getNotFoundCode(): Code
    {
        return Code::NOT_FOUND;
    }

    /**
     * 获取记录不存在时的错误消息
     * @return string
     */
    protected function getNotFoundMessage(): string
    {
        return '字典数据不存在';
    }

    /**
     * 按 ID 获取字典数据（带缓存）
     * @param int $id 字典数据ID
     * @return array|null
     */
    public function getByIdFromCache(int $id): ?array
    {
        $prefix = $this->getCachePrefix();
        $cacheKey = $prefix . 'data:id:' . $id;

        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $record = $this->model->find($id);
        if (!$record || $record->deleted) {
            return null;
        }

        $data = $record->toArray();
        $this->setCache($cacheKey, $data);

        return $data;
    }




    /**
     * 按字典类型编码获取所有字典数据（带缓存）
     * @param string $typeCode 字典类型编码
     * @return array
     */
    public function getByTypeCode(string $typeCode): array
    {
        $prefix = $this->getCachePrefix();
        $cacheKey = $prefix . 'data:code:' . $typeCode;

        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 先通过字典类型编码查出类型 ID
        $dictTypeModel = $this->getDictTypeModel();
        if ($dictTypeModel) {
            $type = $dictTypeModel->where('code', $typeCode)->where('deleted', 0)->first();
            if (!$type) {
                return [];
            }
            $typeId = $type->id;
        } else {
            return [];
        }

        $data = $this->model
            ->where('dict_type_id', $typeId)
            ->where('status', 1)
            ->where('deleted', 0)
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();

        $this->setCache($cacheKey, $data);

        return $data;
    }

    /**
     * 获取字典类型模型
     */
    private function getDictTypeModel()
    {
        $factoryClass = 'plugin\theadmin\app\model\ModelFactory';

        if (!class_exists($factoryClass) || !method_exists($factoryClass, 'dict_type')) {
            return null;
        }

        return $factoryClass::dict_type();
    }

    /**
     * 清理字典数据缓存
     * @param string|null $typeCode 字典类型编码
     */
    public function clearCache(?string $typeCode = null): void
    {
        $prefix = $this->getCachePrefix();

        if ($typeCode !== null) {
            $this->deleteCache($prefix . 'data:code:' . $typeCode);
        } else {
            $this->deleteCacheByPattern($prefix . 'data:code:*');
        }
    }

    /**
     * 创建字典数据
     * @param array $data 字典数据
     * @return DictData
     * @throws ApiException
     */
    public function create(array $data): DictData
    {
        // 检查字典类型是否存在
        $dictTypeModel = $this->getDictTypeModel();
        if ($dictTypeModel) {
            $type = $dictTypeModel->where('id', $data['dict_type_id'])->where('deleted', 0)->first();
            if (!$type) {
                throw new ApiException(Code::BAD_REQUEST, '字典类型不存在');
            }
            $typeCode = $type->code;
        } else {
            $typeCode = null;
        }

        $result = parent::create($data);
        $this->clearCache($typeCode);
        return $result;
    }

    /**
     * 更新字典数据
     * @param int $id 字典数据ID
     * @param array $data 更新数据
     * @return DictData
     * @throws ApiException
     */
    public function update(int $id, array $data): DictData
    {
        // 获取旧记录以便清理缓存
        $oldRecord = $this->model->find($id);
        $oldTypeCode = null;
        if ($oldRecord) {
            $dictTypeModel = $this->getDictTypeModel();
            if ($dictTypeModel) {
                $type = $dictTypeModel->where('id', $oldRecord->dict_type_id)->where('deleted', 0)->first();
                if ($type) {
                    $oldTypeCode = $type->code;
                }
            }
        }

        // 如果更新了 dict_type_id，检查新的类型是否存在
        if (isset($data['dict_type_id'])) {
            $dictTypeModel = $this->getDictTypeModel();
            if ($dictTypeModel) {
                $type = $dictTypeModel->where('id', $data['dict_type_id'])->where('deleted', 0)->first();
                if (!$type) {
                    throw new ApiException(Code::BAD_REQUEST, '字典类型不存在');
                }
            }
        }

        $result = parent::update($id, $data);

        // 清理旧缓存
        if ($oldTypeCode !== null) {
            $this->clearCache($oldTypeCode);
        }

        // 如果类型变更了，也要清理新类型的缓存
        if (isset($data['dict_type_id'])) {
            $dictTypeModel = $this->getDictTypeModel();
            if ($dictTypeModel) {
                $type = $dictTypeModel->where('id', $data['dict_type_id'])->where('deleted', 0)->first();
                if ($type) {
                    $this->clearCache($type->code);
                }
            }
        }

        return $result;
    }

    /**
     * 删除字典数据
     * @param int $id 字典数据ID
     * @return bool
     * @throws ApiException
     */
    public function delete(int $id): bool
    {
        $record = $this->model->find($id);

        if (!$record) {
            throw new ApiException($this->getNotFoundCode(), $this->getNotFoundMessage());
        }

        $dictTypeModel = $this->getDictTypeModel();
        $typeCode = null;
        if ($dictTypeModel) {
            $type = $dictTypeModel->where('id', $record->dict_type_id)->where('deleted', 0)->first();
            if ($type) {
                $typeCode = $type->code;
            }
        }

        $result = parent::delete($id);
        $this->clearCache($typeCode);
        return $result;
    }

    /**
     * 批量删除字典数据
     * @param array $ids 字典数据ID数组
     * @return int 删除数量
     * @throws ApiException
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            throw new ApiException(Code::PARAMETER_ERROR, '请选择要删除的记录');
        }

        // 获取要删除的记录以便清理缓存
        $records = $this->model->whereIn('id', $ids)->get();
        $typeCodes = [];
        foreach ($records as $record) {
            $dictTypeModel = $this->getDictTypeModel();
            if ($dictTypeModel) {
                $type = $dictTypeModel->where('id', $record->dict_type_id)->where('deleted', 0)->first();
                if ($type && !in_array($type->code, $typeCodes)) {
                    $typeCodes[] = $type->code;
                }
            }
        }

        $result = parent::batchDelete($ids);

        foreach ($typeCodes as $typeCode) {
            $this->clearCache($typeCode);
        }

        return $result;
    }

    /**
     * 设置缓存
     */
    private function setCache(string $key, $data): bool
    {
        if ($this->cache === null) {
            return $this->setFileCache($key, $data);
        }

        try {
            $ttl = $this->cacheConfig['dict']['ttl'] ?? 0;
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

            if ($ttl === 0) {
                return $this->cache->set($key, $jsonData);
            }

            return $this->cache->setex($key, $ttl, $jsonData);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取缓存
     */
    private function getCache(string $key)
    {
        if ($this->cache === null) {
            return $this->getFileCache($key);
        }

        try {
            $data = $this->cache->get($key);
            if ($data === false) {
                return null;
            }
            return json_decode($data, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 删除缓存
     */
    private function deleteCache(string $key): bool
    {
        if ($this->cache === null) {
            return $this->deleteFileCache($key);
        }

        try {
            return $this->cache->del($key) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 按模式删除缓存
     */
    private function deleteCacheByPattern(string $pattern): bool
    {
        if ($this->cache === null) {
            return $this->deleteFileCacheByPattern($pattern);
        }

        try {
            $keys = $this->cache->keys($pattern);
            if (!empty($keys)) {
                return $this->cache->del($keys) > 0;
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 设置文件缓存
     */
    private function setFileCache(string $key, $data): bool
    {
        $fileConfig = $this->cacheConfig['file'] ?? [];
        $cachePath = $fileConfig['path'] ?? runtime_path() . '/cache/theadmin/';
        $prefix = $fileConfig['prefix'] ?? 'theadmin_';
        $cacheFile = $cachePath . $prefix . md5($key) . '.json';

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $cacheData = [
            'expire_time' => 0,
            'data' => $data,
        ];

        return file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE)) !== false;
    }

    /**
     * 获取文件缓存
     */
    private function getFileCache(string $key)
    {
        $fileConfig = $this->cacheConfig['file'] ?? [];
        $cachePath = $fileConfig['path'] ?? runtime_path() . '/cache/theadmin/';
        $prefix = $fileConfig['prefix'] ?? 'theadmin_';
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

    /**
     * 删除文件缓存
     */
    private function deleteFileCache(string $key): bool
    {
        $fileConfig = $this->cacheConfig['file'] ?? [];
        $cachePath = $fileConfig['path'] ?? runtime_path() . '/cache/theadmin/';
        $prefix = $fileConfig['prefix'] ?? 'theadmin_';
        $cacheFile = $cachePath . $prefix . md5($key) . '.json';

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return true;
    }

    /**
     * 按模式删除文件缓存
     */
    private function deleteFileCacheByPattern(string $pattern): bool
    {
        $fileConfig = $this->cacheConfig['file'] ?? [];
        $cachePath = $fileConfig['path'] ?? runtime_path() . '/cache/theadmin/';
        $prefix = $fileConfig['prefix'] ?? 'theadmin_';

        if (!is_dir($cachePath)) {
            return true;
        }

        $files = glob($cachePath . $prefix . '*.json');
        foreach ($files as $file) {
            @unlink($file);
        }

        return true;
    }
}
