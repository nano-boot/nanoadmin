<?php

namespace plugin\nanoadmin\app\service\tool;

use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\model\tool\GenerateColumns;
use plugin\nanoadmin\app\service\BaseService;
use plugin\nanoadmin\app\common\ApiException;

/**
 * 代码生成字段服务
 */
class GenerateColumnsService extends BaseService
{
    public function __construct(GenerateColumns $model)
    {
        parent::__construct($model);
    }

    protected function getNotFoundCode(): Code
    {
        return Code::NOT_FOUND;
    }

    protected function getNotFoundMessage(): string
    {
        return '字段配置不存在';
    }

    /**
     * 批量保存字段
     * @param int $tableId 表ID
     * @param array $columns 字段数据
     * @return int 影响的行数
     */
    public function saveBatch(int $tableId, array $columns): int
    {
        $now = time();
        $rows = [];

        foreach ($columns as $column) {
            $rows[] = [
                'table_id' => $tableId,
                'column_name' => $column['column_name'] ?? '',
                'column_comment' => $column['column_comment'] ?? '',
                'column_type' => $column['column_type'] ?? '',
                'php_type' => $column['php_type'] ?? 'string',
                'primary_key' => $column['primary_key'] ?? 1,
                'required' => $column['required'] ?? 1,
                'insertable' => $column['insertable'] ?? 1,
                'editable' => $column['editable'] ?? 1,
                'show_list' => $column['show_list'] ?? 1,
                'queriable' => $column['queriable'] ?? 1,
                'query_type' => $column['query_type'] ?? 'eq',
                'view_type' => $column['view_type'] ?? 'input',
                'dict_type' => $column['dict_type'] ?? '',
                'options' => isset($column['options']) ? json_encode($column['options']) : null,
                'sort' => $column['sort'] ?? 0,
                'override' => $column['override'] ?? 0,
                'default_value' => $column['default_value'] ?? '',
                'deleted_at' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        return \support\Db::table('sys_generate_columns')->insert($rows);
    }

    /**
     * 更新字段配置
     * @param int $tableId 表ID
     * @param array $columns 字段数据
     * @return bool
     */
    public function updateBatch(int $tableId, array $columns): bool
    {
        $now = time();

        foreach ($columns as $column) {
            $id = $column['id'] ?? 0;
            if ($id > 0) {
                \support\Db::table('sys_generate_columns')
                    ->where('id', $id)
                    ->where('table_id', $tableId)
                    ->update([
                        'column_comment' => $column['column_comment'] ?? '',
                        'primary_key' => $column['primary_key'] ?? 1,
                        'required' => $column['required'] ?? 1,
                        'insertable' => $column['insertable'] ?? 1,
                        'editable' => $column['editable'] ?? 1,
                        'show_list' => $column['show_list'] ?? 1,
                        'queriable' => $column['queriable'] ?? 1,
                        'query_type' => $column['query_type'] ?? 'eq',
                        'view_type' => $column['view_type'] ?? 'input',
                        'dict_type' => $column['dict_type'] ?? '',
                        'options' => isset($column['options']) ? json_encode($column['options']) : null,
                        'sort' => $column['sort'] ?? 0,
                        'override' => $column['override'] ?? 0,
                        'default_value' => $column['default_value'] ?? '',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }
        }

        return true;
    }

    /**
     * 根据表ID获取字段列表
     * @param int $tableId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByTableId(int $tableId)
    {
        return $this->model->where('table_id', $tableId)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * 删除表的字段
     * @param int $tableId
     * @return int
     */
    public function deleteByTableId(int $tableId): int
    {
        return $this->model->where('table_id', $tableId)->delete();
    }
}
