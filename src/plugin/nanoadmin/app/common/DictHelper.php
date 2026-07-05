<?php

namespace plugin\nanoadmin\app\common;

use plugin\nanoadmin\app\model\ModelFactory;
use plugin\nanoadmin\app\service\DictDataService;

/**
 * 字典数据辅助类
 * 
 * 提供便捷的静态方法获取字典数据，内部自动处理缓存
 * 
 * 使用示例：
 *   DictHelper::getLabel('gender', 1);           // 返回 "男"
 *   DictHelper::getLabels('gender');              // 返回 [['value' => 1, 'label' => '男'], ...]
 *   DictHelper::getOptions('gender');              // 返回 [{ label: '男', value: 1 }, ...] (前端格式)
 *   DictHelper::getMap('gender');                 // 返回 [1 => '男', 2 => '女']
 *   DictHelper::getValue('gender', '男');         // 返回 1
 */
class DictHelper
{
    /**
     * 内存缓存，避免同一请求多次查询数据库
     * @var array
     */
    private static array $memoryCache = [];

    /**
     * 根据字典类型编码和值获取标签
     * 
     * @param string $typeCode 字典类型编码，如 'gender'
     * @param mixed $value 字典值，如 1
     * @param string $default 默认值，当找不到时返回
     * @return string 字典标签，如 '男'
     */
    public static function getLabel(string $typeCode, mixed $value, string $default = ''): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $dataList = self::getList($typeCode);

        foreach ($dataList as $item) {
            if ((string) $item['value'] === (string) $value) {
                return $item['label'] ?? $default;
            }
        }

        return $default;
    }

    /**
     * 根据字典类型编码和标签获取值
     * 
     * @param string $typeCode 字典类型编码，如 'gender'
     * @param string $label 字典标签，如 '男'
     * @param mixed $default 默认值，当找不到时返回
     * @return mixed 字典值，如 1
     */
    public static function getValue(string $typeCode, string $label, mixed $default = null): mixed
    {
        if ($label === null || $label === '') {
            return $default;
        }

        $dataList = self::getList($typeCode);

        foreach ($dataList as $item) {
            if (($item['label'] ?? '') === $label) {
                return $item['value'];
            }
        }

        return $default;
    }

    /**
     * 获取字典类型的所有数据列表
     * 
     * @param string $typeCode 字典类型编码
     * @return array 数据列表
     */
    public static function getList(string $typeCode): array
    {
        $cacheKey = 'dict:list:' . $typeCode;

        // 优先从内存缓存获取
        if (isset(self::$memoryCache[$cacheKey])) {
            return self::$memoryCache[$cacheKey];
        }

        // 获取数据列表
        $dataList = self::fetchFromService($typeCode);

        // 存入内存缓存
        self::$memoryCache[$cacheKey] = $dataList;

        return $dataList;
    }

    /**
     * 获取字典类型的映射表（值 => 标签）
     * 
     * @param string $typeCode 字典类型编码
     * @return array [value => label]，如 [1 => '男', 2 => '女']
     */
    public static function getMap(string $typeCode): array
    {
        $cacheKey = 'dict:map:' . $typeCode;

        // 优先从内存缓存获取
        if (isset(self::$memoryCache[$cacheKey])) {
            return self::$memoryCache[$cacheKey];
        }

        $dataList = self::getList($typeCode);
        $map = [];

        foreach ($dataList as $item) {
            $map[(string) $item['value']] = $item['label'] ?? '';
        }

        // 存入内存缓存
        self::$memoryCache[$cacheKey] = $map;

        return $map;
    }

    /**
     * 获取字典类型的前端选项格式
     * 
     * @param string $typeCode 字典类型编码
     * @return array [{ label: '男', value: 1 }, ...]
     */
    public static function getOptions(string $typeCode): array
    {
        $cacheKey = 'dict:options:' . $typeCode;

        // 优先从内存缓存获取
        if (isset(self::$memoryCache[$cacheKey])) {
            return self::$memoryCache[$cacheKey];
        }

        $dataList = self::getList($typeCode);
        $options = [];

        foreach ($dataList as $item) {
            $options[] = [
                'label' => $item['label'] ?? '',
                'value' => $item['value'],
            ];
        }

        // 存入内存缓存
        self::$memoryCache[$cacheKey] = $options;

        return $options;
    }

    /**
     * 批量获取多个字典类型的标签
     * 
     * @param array $typeCodes 字典类型编码数组，如 ['gender', 'status']
     * @return array 结果，如 ['gender' => [1 => '男', 2 => '女'], 'status' => [...]]
     */
    public static function getMultipleMaps(array $typeCodes): array
    {
        $result = [];

        foreach ($typeCodes as $typeCode) {
            $result[$typeCode] = self::getMap($typeCode);
        }

        return $result;
    }

    /**
     * 批量获取多个字典值对应的标签
     * 
     * @param string $typeCode 字典类型编码
     * @param array $values 值数组，如 [1, 2, 3]
     * @param string $separator 多值分隔符
     * @return string|array 标签或标签数组
     */
    public static function getLabels(string $typeCode, array $values, string $separator = ','): string|array
    {
        if (empty($values)) {
            return '';
        }

        $map = self::getMap($typeCode);
        $labels = [];

        foreach ($values as $value) {
            $labels[] = $map[(string) $value] ?? '';
        }

        // 如果只查一个值，直接返回字符串
        if (count($values) === 1) {
            return $labels[0] ?? '';
        }

        return implode($separator, array_filter($labels));
    }

    /**
     * 批量转换记录中的字典值
     * 
     * @param array $records 记录数组
     * @param array $dictFields 字典字段配置，如 ['gender' => 'gender_name', 'status' => 'status_name']
     *                          key: 字典类型编码, value: 要转换的目标字段名
     * @return array 转换后的记录
     * 
     * @example
     *   $users = [
     *     ['id' => 1, 'gender' => 1, 'status' => 1],
     *     ['id' => 2, 'gender' => 2, 'status' => 2],
     *   ];
     *   $result = DictHelper::batchTransform($users, ['gender' => 'gender_name', 'status' => 'status_name']);
     *   // 结果: [['id' => 1, 'gender' => 1, 'gender_name' => '男', 'status' => 1, 'status_name' => '启用'], ...]
     */
    public static function batchTransform(array $records, array $dictFields): array
    {
        if (empty($records) || empty($dictFields)) {
            return $records;
        }

        // 预加载所有需要的字典类型
        $typeCodes = array_keys($dictFields);
        $maps = self::getMultipleMaps($typeCodes);

        $result = [];
        foreach ($records as $record) {
            foreach ($dictFields as $typeCode => $targetField) {
                $value = $record[$typeCode] ?? null;
                if ($value !== null) {
                    $record[$targetField] = $maps[$typeCode][(string) $value] ?? '';
                } else {
                    $record[$targetField] = '';
                }
            }
            $result[] = $record;
        }

        return $result;
    }

    /**
     * 从服务层获取字典数据
     * 
     * @param string $typeCode 字典类型编码
     * @return array
     */
    private static function fetchFromService(string $typeCode): array
    {
        try {
            $service = self::getDictDataService();
            if ($service) {
                return $service->getByTypeCode($typeCode);
            }
        } catch (\Exception $e) {
            // 服务获取失败，返回空数组
        }

        return [];
    }

    /**
     * 获取字典数据服务实例
     * 
     * @return DictDataService|null
     */
    private static function getDictDataService(): ?DictDataService
    {
        try {
            $model = ModelFactory::dict_data();
            return new DictDataService($model);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 清除内存缓存
     * 
     * 通常在测试场景或需要强制刷新时调用
     */
    public static function clearMemoryCache(): void
    {
        self::$memoryCache = [];
    }

    /**
     * 检查字典值是否存在
     * 
     * @param string $typeCode 字典类型编码
     * @param mixed $value 字典值
     * @return bool
     */
    public static function hasValue(string $typeCode, mixed $value): bool
    {
        $map = self::getMap($typeCode);
        return isset($map[(string) $value]) && $map[(string) $value] !== '';
    }

    /**
     * 检查字典标签是否存在
     * 
     * @param string $typeCode 字典类型编码
     * @param string $label 字典标签
     * @return bool
     */
    public static function hasLabel(string $typeCode, string $label): bool
    {
        $map = self::getMap($typeCode);
        return in_array($label, $map, true);
    }
}
