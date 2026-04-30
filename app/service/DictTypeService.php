<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\model\DictType;
use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;

/**
 * 字典类型服务类
 */
class DictTypeService extends BaseService
{
    /**
     * 构造函数
     * @param DictType $model 字典类型模型实例
     */
    public function __construct(DictType $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取记录不存在时的错误代码
     * @return Code
     */
    protected function getNotFoundCode(): Code
    {
        return Code::RESOURCE_NOT_FOUND;
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
     * 创建字典类型
     * @param array $data 字典类型数据
     * @return DictType
     * @throws ApiException
     */
    public function create(array $data): DictType
    {
        // 检查编码是否已存在
        if ($this->model->where('code', $data['code'])->where('deleted', 0)->exists()) {
            throw new ApiException(Code::RESOURCE_EXISTS, '字典编码已存在');
        }

        return parent::create($data);
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
                throw new ApiException(Code::RESOURCE_EXISTS, '字典编码已被其他记录使用');
            }
        }

        return parent::update($id, $data);
    }
}
