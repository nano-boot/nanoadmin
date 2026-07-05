<?php

namespace plugin\nanoadmin\app\service;

use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\model\Config;

/**
 * 系统配置服务
 */
class ConfigService extends BaseService
{
    /**
     * 构造函数
     * @param Config $model 配置模型实例
     */
    public function __construct(Config $model)
    {
        parent::__construct($model);
    }

    protected string $modelName = 'Config';

    protected function getNotFoundCode(): Code
    {
        return Code::NOT_FOUND;
    }

    protected function getNotFoundMessage(): string
    {
        return '配置不存在';
    }

    /**
     * 创建配置
     * @param array $data
     * @return \plugin\nanoadmin\app\model\BaseModel
     */
    public function create(array $data): \plugin\nanoadmin\app\model\BaseModel
    {
        if ($this->model->checkExists(['key' => $data['key']])) {
            throw new \plugin\nanoadmin\app\common\ApiException(Code::PARAMETER_ERROR, '配置键名已存在');
        }

        return parent::create($data);
    }

    /**
     * 更新配置
     * @param int $id
     * @param array $data
     * @return \plugin\nanoadmin\app\model\BaseModel
     */
    public function update(int $id, array $data): \plugin\nanoadmin\app\model\BaseModel
    {
        if (isset($data['key'])) {
            if ($this->model->checkExists(['key' => $data['key']], $id)) {
                throw new \plugin\nanoadmin\app\common\ApiException(Code::PARAMETER_ERROR, '配置键名已存在');
            }
        }

        return parent::update($id, $data);
    }

    /**
     * 根据键名获取配置值
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    public function getByKey(string $key, mixed $default = null): ?string
    {
        $config = $this->model->getOne(['key' => $key, 'status' => 1]);
        return $config ? $config->value : $default;
    }

    /**
     * 批量获取配置
     * @param array $keys
     * @return array
     */
    public function getByKeys(array $keys): array
    {
        $configs = $this->model->getAll(['key' => $keys, 'status' => 1]);
        $result = [];
        foreach ($configs as $config) {
            $result[$config->key] = $config->value;
        }
        return $result;
    }

    /**
     * 更新配置值
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function updateValue(string $key, string $value): bool
    {
        $config = $this->model->getOne(['key' => $key]);
        if (!$config) {
            return false;
        }

        return $config->update(['value' => $value]);
    }

    /**
     * 根据分组获取配置列表（用于表单展示）
     * @param string $group
     * @return array
     */
    public function getByGroup(string $group): array
    {
        $configs = $this->model->where('group', $group)
            ->where('status', 1)
            ->orderByRaw('sort asc, id asc')
            ->select('id', 'key', 'name', 'value', 'type', 'options', 'description', 'sort')
            ->get()
            ->all();

        return array_map(function ($config) {
            return [
                'id' => $config->id,
                'key' => $config->key,
                'name' => $config->name,
                'value' => $config->value,
                'type' => $config->type,
                'options' => $config->options,
                'description' => $config->description,
                'sort' => $config->sort
            ];
        }, $configs);
    }

    /**
     * 批量更新配置值
     * @param array $items 配置项数组 [['key' => 'xxx', 'value' => 'xxx'], ...]
     * @return int 更新数量
     */
    public function batchUpdateValues(array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            if (isset($item['key']) && isset($item['value'])) {
                if ($this->updateValue($item['key'], $item['value'])) {
                    $count++;
                }
            }
        }
        return $count;
    }
}
