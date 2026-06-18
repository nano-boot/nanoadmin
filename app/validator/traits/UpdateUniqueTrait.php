<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\traits;

use support\validation\Rule as IlluminateRule;

/**
 * 更新场景唯一性校验 Trait
 *
 * 用于 webman/validation 的验证器，提供 update 场景下的唯一性校验支持
 */
trait UpdateUniqueTrait
{
    /**
     * 主键字段（子类可覆盖）
     */
    protected string $primaryKey = 'id';

    /**
     * 在 update 场景中构建排除自身后的 unique 规则
     *
     * @param array $allRules 完整规则
     * @param array $uniqueFields 需要 unique 校验的字段列表
     * @return array
     */
    protected function buildUpdateUnique(array $allRules, array $uniqueFields): array
    {
        $excludeId = $this->getExcludeId();
        $sceneRules = $allRules;

        foreach ($uniqueFields as $field) {
            if (!isset($allRules[$field])) {
                continue;
            }

            // 获取原始规则
            $fieldRules = $allRules[$field];
            if (!is_array($fieldRules)) {
                $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : [$fieldRules];
            }

            // 过滤并替换 unique 规则
            $newRules = [];
            foreach ($fieldRules as $rule) {
                if ($rule instanceof \Illuminate\Validation\Rules\Unique) {
                    // 注入 excludeId
                    $newRules[] = $rule->ignore($excludeId, $this->primaryKey);
                } else {
                    $newRules[] = $rule;
                }
            }

            $sceneRules[$field] = $newRules;
        }

        return $sceneRules;
    }

    /**
     * 获取要排除的记录ID
     *
     * @return int
     */
    protected function getExcludeId(): int
    {
        $data = $this->data();
        return (int)($data[$this->primaryKey] ?? 0);
    }

    /**
     * 验证更新数据（支持 excludeId）
     *
     * @param array $data
     * @param int $excludeId
     * @param string|null $scene
     * @return array
     */
    public function validateUpdateData(array $data, int $excludeId, ?string $scene = 'update'): array
    {
        return $this->validateData($data, $scene, ['excludeId' => $excludeId]);
    }
}
