<?php

namespace plugin\theadmin\app\service;

use plugin\theadmin\app\model\DictType;
use plugin\theadmin\app\model\DictData;
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
    public function __construct(DictType $model, DictData $dictDataModel)
    {
        parent::__construct($model);
        $this->dictDataModel = $dictDataModel;
    }

    /**
     * 字典数据模型实例
     * @var DictData
     */
    private DictData $dictDataModel;

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
                throw new ApiException(Code::BAD_REQUEST, '字典编码已被其他记录使用');
            }
        }

        return parent::update($id, $data);
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

        try {
            \support\Db::beginTransaction();
            // 先删除关联的字典数据
            $this->dictDataModel->where('dict_type_id', $id)->delete();
            // 再删除字典类型本身
            $result = $this->model->destroy($id);
            \support\Db::commit();
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
            return $result;
        } catch (\Exception $e) {
            \support\Db::rollback();
            throw new ApiException(Code::SYSTEM_ERROR, $this->getBatchDeleteFailedMessage() . ': ' . $e->getMessage());
        }
    }
}
