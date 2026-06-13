<?php

namespace plugin\nanoadmin\app\controller;

use plugin\nanoadmin\app\service\DictDataService;

/**
 * 字典数据控制器
 */
class DictDataController extends BaseController
{
    /**
     * 字典数据服务实例
     * @var DictDataService
     */
    private DictDataService $dictDataService;

    public function __construct(DictDataService $dictDataService)
    {
        $this->dictDataService = $dictDataService;
    }

    /**
     * 获取服务实例
     * @return DictDataService
     */
    protected function getService(): DictDataService
    {
        return $this->dictDataService;
    }

    /**
     * 获取模型名称
     * @return string
     */
    protected function getModelName(): string
    {
        return 'DictData';
    }
}
