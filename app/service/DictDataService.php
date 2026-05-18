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
     * 构造函数
     * @param DictData $model 字典数据模型实例
     */
    public function __construct(DictData $model)
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
        return '字典数据不存在';
    }
}
