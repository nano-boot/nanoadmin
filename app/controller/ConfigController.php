<?php

namespace plugin\nanoadmin\app\controller;

use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\common\R;
use plugin\nanoadmin\app\service\ConfigService;

/**
 * 系统配置控制器
 */
class ConfigController extends BaseController
{
    protected ConfigService $configService;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    protected function getService(): ConfigService
    {
        return $this->configService;
    }

    protected function getModelName(): string
    {
        return 'Config';
    }

    /**
     * 根据分组获取配置列表（用于表单展示）
     * @return \support\Response
     */
    public function getByGroup(): \support\Response
    {
        $group = request()->get('group', 'basic');
        $configs = $this->configService->getByGroup($group);
        return R::success($configs);
    }

    /**
     * 批量更新配置
     * @return \support\Response
     */
    public function batchUpdate(): \support\Response
    {
        try {
            $data = request()->post();
            if (!isset($data['items']) || !is_array($data['items'])) {
                return R::error('参数错误：缺少 items 字段', Code::PARAMETER_ERROR->value);
            }
            $this->configService->batchUpdateValues($data['items']);
            return R::success(null, '保存成功');
        } catch (\Exception $e) {
            return R::error('保存失败：' . $e->getMessage(), Code::SYSTEM_ERROR->value);
        }
    }
}
