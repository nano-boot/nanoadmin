<?php

namespace plugin\nanoadmin\app\service;

use plugin\nanoadmin\app\model\DictData;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use think\facade\Cache;
use think\cache\Driver;

/**
 * 字典数据服务类
 *
 * 缓存基于 webman/think-cache：
 *   - 通过 think\facade\Cache 调用，默认走 config/think-cache.php 中 default 指定的 store
 *   - 缓存键可以使用 ":" 分隔（Redis 命名空间惯例）；think-cache 仅禁用 ";"
 *   - 用 Cache::tag('dict') 给所有字典键打标，clear() 即可批量失效
 *   - ttl=0 表示无过期（依赖 CRUD 末尾 clearCache 维护一致性）
 */
class DictDataService extends BaseService
{
    /**
     * 字典缓存配置（plugin/nanoadmin/config/cache.php 中的 dict 子树）
     * @var array
     */
    private array $dictConfig = [];

    /**
     * 缓存驱动实例，null 表示禁用缓存
     * @var Driver|null
     */
    private ?Driver $cache = null;

    /**
     * 缓存标签名（用于批量失效）
     */
    private string $cacheTag = 'dict';

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
     * 加载字典缓存配置（通过 webman config() 助手读取 plugin/nanoadmin/config/nanoadmin.php）
     */
    private function loadCacheConfig(): void
    {
        $this->dictConfig = config('nanoadmin.cache.dict', []);
    }

    /**
     * 初始化缓存实例
     */
    private function initializeCache(): void
    {
        if (!($this->dictConfig['enabled'] ?? true)) {
            $this->cache = null;
            return;
        }

        try {
            $store = $this->dictConfig['store'] ?? null;
            $this->cache = ($store !== null && $store !== '')
                ? Cache::store($store)
                : Cache::store();
        } catch (\Throwable $e) {
            $this->cache = null;
        }
    }

    /**
     * 构造缓存键（保留 ":" 分隔，与 Redis 命名空间惯例一致）
     */
    private function buildKey(string $suffix): string
    {
        $prefix = $this->dictConfig['prefix'] ?? 'dict:';
        return rtrim($prefix, ':') . ':' . ltrim($suffix, ':');
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
        $cacheKey = $this->buildKey('data:id:' . $id);

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
        $cacheKey = $this->buildKey('data:code:' . $typeCode);

        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $dictTypeModel = $this->getDictTypeModel();
        if (!$dictTypeModel) {
            return [];
        }

        $type = $dictTypeModel->where('code', $typeCode)->where('deleted', 0)->first();
        if (!$type) {
            return [];
        }

        $data = $this->model
            ->where('dict_type_id', $type->id)
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
        $factoryClass = 'plugin\nanoadmin\app\model\ModelFactory';

        if (!class_exists($factoryClass) || !method_exists($factoryClass, 'dict_type')) {
            return null;
        }

        return $factoryClass::dict_type();
    }

    /**
     * 清理字典数据缓存
     * @param string|null $typeCode 字典类型编码；传 null 时清理该标签下的全部字典键
     */
    public function clearCache(?string $typeCode = null): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            if ($typeCode !== null) {
                $this->cache->delete($this->buildKey('data:code:' . $typeCode));
                return;
            }

            // 全量清理：借助 tag 一次清掉所有字典键（不再需要枚举 dict_type）
            $this->cache->tag($this->cacheTag)->clear();
        } catch (\Throwable $e) {
            // 静默失败：缓存清理失败不应阻塞业务流程
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
     * 从缓存读取
     */
    private function getCache(string $key)
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            return $this->cache->get($key);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 写入缓存（写入时给 key 打上 "dict" 标签，方便批量清理）
     */
    private function setCache(string $key, $data): bool
    {
        if ($this->cache === null) {
            return false;
        }

        try {
            $ttl = (int) ($this->dictConfig['ttl'] ?? 0);
            return $this->cache->tag($this->cacheTag)->set($key, $data, $ttl);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 删除单个缓存键
     */
    private function deleteCache(string $key): bool
    {
        if ($this->cache === null) {
            return true;
        }

        try {
            return $this->cache->delete($key);
        } catch (\Throwable $e) {
            return false;
        }
    }
}