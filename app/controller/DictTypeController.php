<?php

namespace plugin\theadmin\app\controller;

use plugin\theadmin\app\service\DictTypeService;

/**
 * 字典类型控制器
 */
class DictTypeController extends BaseController
{
    /**
     * 字典类型服务实例
     * @var DictTypeService
     */
    private DictTypeService $dictTypeService;

    public function __construct(DictTypeService $dictTypeService)
    {
        $this->dictTypeService = $dictTypeService;
    }

    /**
     * 获取服务实例
     * @return DictTypeService
     */
    protected function getService(): DictTypeService
    {
        return $this->dictTypeService;
    }

    /**
     * 获取模型名称
     * @return string
     */
    protected function getModelName(): string
    {
        return 'DictType';
    }
}
