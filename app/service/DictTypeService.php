<?php

namespace plugin\nanoadmin\app\service;

use plugin\nanoadmin\app\model\DictType;
use plugin\nanoadmin\app\model\DictData;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;

/**
 * 字典类型服务类
 */
class DictTypeService extends BaseService
{
    /**
     * 字典数据模型实例
     * @var DictData
     */
    private DictData $dictDataModel;

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
     * @param DictType $model 字典类型模型实例
     * @param DictData $dictDataModel 字典数据模型实例
     */
    public function __construct(DictType $model, DictData $dictDataModel)
    {
        parent::__construct($model);
        $this->dictDataModel = $dictDataModel;
        $this->loadCacheConfig();
        $this->initializeCache();
    }

    /**
     * 加载缓存配置
     */
    private function loadCacheConfig(): void
    {
        $configFile = base_path() . '/plugin/nanoadmin/config/cache.php';

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
        $redisPrefix = $this->cacheConfig['redis']['prefix'] ?? 'nanoadmin:';
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
        return '字典类型不存在';
    }

    /**
     * 获取所有字典类型（带缓存）
     * @return array
     */
    public function getAllTypes(): array
    {
        $prefix = $this->getCachePrefix();
        $cacheKey = $prefix . 'type:all';

        // 尝试从缓存获取
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 缓存未命中，查库
        $types = $this->model->where('status', 1)->where('deleted', 0)->get()->toArray();

        // 写入缓存
        $this->setCache($cacheKey, $types);

        return $types;
    }

    /**
     * 按 ID 获取字典类型（带缓存）
     * @param int $id 字典类型ID
     * @return array|null
     */
    public function getByIdFromCache(int $id): ?array
    {
        $prefix = $this->getCachePrefix();
        $cacheKey = $prefix . 'type:id:' . $id;

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
     * 按编码获取字典类型（带缓存）
     * @param string $code 字典编码
     * @return array|null
     */
    public function getByCode(string $code): ?array
    {
        $prefix = $this->getCachePrefix();
        $cacheKey = $prefix . 'type:code:' . $code;

        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $record = $this->model->where('code', $code)->where('deleted', 0)->first();
        if (!$record) {
            return null;
        }

        $data = $record->toArray();
        $this->setCache($cacheKey, $data);

        return $data;
    }

    /**
     * 清理字典类型缓存
     * @param int|null $id 字典类型ID
     * @param string|null $code 字典编码
     */
    public function clearCache(?int $id = null, ?string $code = null): void
    {
        $prefix = $this->getCachePrefix();

        // 清理全部类型列表缓存
        $this->deleteCache($prefix . 'type:all');

        // 清理指定 ID 的缓存
        if ($id !== null) {
            $this->deleteCache($prefix . 'type:id:' . $id);
        }

        // 清理指定编码的缓存
        if ($code !== null) {
            $this->deleteCache($prefix . 'type:code:' . $code);
        }
    }

    /**
     * 创建字典类型
     * @param array $data 字典类型数据
     * @return DictType
     * @throws ApiException
     */
    public function create(array $data): DictType
    {
        // 检查编码是否已存在
        if ($this->model->where('code', $data['code'])->where('deleted', 0)->exists()) {
            throw new ApiException(Code::BAD_REQUEST, '字典编码已存在');
        }

        $result = parent::create($data);
        $this->clearCache(null, $data['code']);
        return $result;
    }

    /**
     * 更新字典类型
     * @param int $id 字典类型ID
     * @param array $data 更新数据
     * @return DictType
     * @throws ApiException
     */
    public function update(int $id, array $data): DictType
    {
        // 检查编码是否已被其他记录使用
        if (isset($data['code'])) {
            $exists = $this->model
                ->where('code', $data['code'])
                ->where('id', '!=', $id)
                ->where('deleted', 0)
                ->exists();

            if ($exists) {
                throw new ApiException(Code::BAD_REQUEST, '字典编码已被其他记录使用');
            }
        }

        // 获取旧记录以便清理旧编码的缓存
        $oldRecord = $this->model->find($id);
        if ($oldRecord) {
            $oldCode = $oldRecord->code;
        } else {
            $oldCode = null;
        }

        $result = parent::update($id, $data);

        // 清理缓存
        $this->clearCache($id, $oldCode);
        if (isset($data['code']) && $data['code'] !== $oldCode) {
            $this->clearCache($id, $data['code']);
        }

        return $result;
    }

    /**
     * 删除字典类型（同时删除关联的字典数据）
     * @param int $id 字典类型ID
     * @return bool
     * @throws ApiException
     */
    public function delete(int $id): bool
    {
        $record = $this->model->find($id);

        if (!$record) {
            throw new ApiException($this->getNotFoundCode(), $this->getNotFoundMessage());
        }

        $code = $record->code;

        try {
            \support\Db::beginTransaction();
            // 先删除关联的字典数据
            $this->dictDataModel->where('dict_type_id', $id)->delete();
            // 再删除字典类型本身
            $result = $this->model->destroy($id);
            \support\Db::commit();

            $this->clearCache($id, $code);
            return $result;
        } catch (\Exception $e) {
            \support\Db::rollback();
            throw new ApiException(Code::SYSTEM_ERROR, $this->getDeleteFailedMessage() . ': ' . $e->getMessage());
        }
    }

    /**
     * 批量删除字典类型（同时删除关联的字典数据）
     * @param array $ids 字典类型ID数组
     * @return int 删除数量
     * @throws ApiException
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            throw new ApiException(Code::PARAMETER_ERROR, '请选择要删除的记录');
        }

        $existingRecords = $this->model->whereIn('id', $ids)->pluck('id')->toArray();
        $invalidIds = array_diff($ids, $existingRecords);

        if (!empty($invalidIds)) {
            throw new ApiException($this->getNotFoundCode(), $this->getNotFoundMessage() . ': ' . implode(',', $invalidIds));
        }

        // 获取要删除的记录以便清理缓存
        $records = $this->model->whereIn('id', $ids)->get();
        $codes = $records->pluck('code')->toArray();

        try {
            \support\Db::beginTransaction();
            // 先批量删除关联的字典数据
            $this->dictDataModel->whereIn('dict_type_id', $ids)->delete();
            // 再批量删除字典类型
            $result = $this->model->destroy($ids);
            \support\Db::commit();

            // 清理缓存
            $this->clearCache();
            foreach ($ids as $id) {
                $this->clearCache($id);
            }

            return $result;
        } catch (\Exception $e) {
            \support\Db::rollback();
            throw new ApiException(Code::SYSTEM_ERROR, $this->getBatchDeleteFailedMessage() . ': ' . $e->getMessage());
        }
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
     * 设置文件缓存
     */
    private function setFileCache(string $key, $data): bool
    {
        $fileConfig = $this->cacheConfig['file'] ?? [];
        $cachePath = $fileConfig['path'] ?? runtime_path() . '/cache/nanoadmin/';
        $prefix = $fileConfig['prefix'] ?? 'nanoadmin_';
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
        $cachePath = $fileConfig['path'] ?? runtime_path() . '/cache/nanoadmin/';
        $prefix = $fileConfig['prefix'] ?? 'nanoadmin_';
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
        $cachePath = $fileConfig['path'] ?? runtime_path() . '/cache/nanoadmin/';
        $prefix = $fileConfig['prefix'] ?? 'nanoadmin_';
        $cacheFile = $cachePath . $prefix . md5($key) . '.json';

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return true;
    }
}
