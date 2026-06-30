<?php

namespace plugin\nanoadmin\app\service;

use plugin\nanoadmin\app\model\DictType;
use plugin\nanoadmin\app\model\DictData;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use think\facade\Cache;
use think\cache\Driver;

/**
 * 字典类型服务类
 *
 * 缓存基于 webman/think-cache：
 *   - 通过 think\facade\Cache 调用，默认走 config/think-cache.php 中 default 指定的 store
 *   - 缓存键使用 ":" 分隔（Redis 命名空间惯例）；think-cache 仅禁用 ";"
 *   - 用 Cache::tag('dict_type') 给所有类型键打标，clear() 即可批量失效
 *   - ttl=0 表示无过期（依赖 CRUD 末尾 clearCache 维护一致性）
 */
class DictTypeService extends BaseService
{
    /**
     * 字典数据模型实例
     * @var DictData
     */
    private DictData $dictDataModel;

    /**
     * 字典类型缓存配置（plugin/nanoadmin/config/cache.php 中的 dict 子树）
     * @var array
     */
    private array $dictConfig = [];

    /**
     * 缓存驱动实例，null 表示禁用缓存
     * @var Driver|null
     */
    private ?Driver $cache = null;

    /**
     * 缓存标签名（用于批量失效，区别于 DictDataService 的 'dict' 标签）
     */
    private string $cacheTag = 'dict_type';

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
        return '字典类型不存在';
    }

    /**
     * 获取所有字典类型（带缓存）
     * @return array
     */
    public function getAllTypes(): array
    {
        $cacheKey = $this->buildKey('type:all');

        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $types = $this->model->where('status', 1)->where('deleted', 0)->get()->toArray();

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
        $cacheKey = $this->buildKey('type:id:' . $id);

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
        $cacheKey = $this->buildKey('type:code:' . $code);

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
     *
     * 行为：
     *   - 传 (id, code) → 删除 id 和 code 两个 key
     *   - 都不传        → 借 tag 一次清掉所有 type 键（替换原来"先 delete all + 逐个 delete id"的多次 IO）
     */
    public function clearCache(?int $id = null, ?string $code = null): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            if ($id === null && $code === null) {
                $this->cache->tag($this->cacheTag)->clear();
                return;
            }

            if ($id !== null) {
                $this->cache->delete($this->buildKey('type:id:' . $id));
            }
            if ($code !== null) {
                $this->cache->delete($this->buildKey('type:code:' . $code));
            }
        } catch (\Throwable $e) {
            // 静默失败：缓存清理失败不应阻塞业务流程
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
        $oldCode = $oldRecord?->code;

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

        try {
            \support\Db::beginTransaction();
            // 先批量删除关联的字典数据
            $this->dictDataModel->whereIn('dict_type_id', $ids)->delete();
            // 再批量删除字典类型
            $result = $this->model->destroy($ids);
            \support\Db::commit();

            // 一次性清掉所有 type 缓存
            $this->clearCache();

            return $result;
        } catch (\Exception $e) {
            \support\Db::rollback();
            throw new ApiException(Code::SYSTEM_ERROR, $this->getBatchDeleteFailedMessage() . ': ' . $e->getMessage());
        }
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
     * 写入缓存（写入时给 key 打上 "dict_type" 标签，方便批量清理）
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
}