<?php

namespace plugin\nanoadmin\app\service\tool;

use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
use plugin\nanoadmin\app\common\CodeEngine;
use plugin\nanoadmin\app\common\CodeZip;
use plugin\nanoadmin\app\model\tool\GenerateTables;
use plugin\nanoadmin\app\service\BaseService;
use support\Db;

/**
 * 代码生成服务
 */
class GenerateTablesService extends BaseService
{
    private GenerateColumnsService $columnsService;
    private CodeEngine $codeEngine;

    public function __construct(
        GenerateTables $model,
        GenerateColumnsService $columnsService
    ) {
        parent::__construct($model);
        $this->columnsService = $columnsService;
        $this->codeEngine = new CodeEngine();
    }

    protected function getNotFoundCode(): Code
    {
        return Code::NOT_FOUND;
    }

    protected function getNotFoundMessage(): string
    {
        return '代码生成配置不存在';
    }

    /**
     * 装载数据库表
     * @param array $names 表名数组
     * @param string $source 数据源
     * @return array
     */
    public function loadTable(array $names, string $source = ''): array
    {
        $results = [];

        foreach ($names as $tableName) {
            $exists = $this->model->where('table_name', $tableName)->exists();

            if ($exists) {
                $results[] = [
                    'table_name' => $tableName,
                    'status' => 'skipped',
                    'message' => '表已存在，跳过',
                ];
                continue;
            }

            try {
                $columns = $this->getTableColumnsFromDatabase($tableName, $source);

                $className = $this->generateClassName($tableName);
                $tableComment = $this->getTableCommentFromDatabase($tableName, $source);

                $table = $this->model->create([
                    'table_name' => $tableName,
                    'table_comment' => $tableComment,
                    'class_name' => $className,
                    'business_name' => '',
                    'namespace' => 'tool',
                    'package_name' => 'tool',
                    'template' => 'plugin',
                    'tpl_category' => 'single',
                    'menu_name' => '',
                    'belong_menu_id' => 0,
                    'generate_menus' => 'index,save,update,read,destroy',
                    'options' => null,
                    'source' => $source,
                ]);

                $columnData = [];
                $sort = 0;
                foreach ($columns as $column) {
                    $columnData[] = [
                        'column_name' => $column['name'],
                        'column_comment' => $column['comment'],
                        'column_type' => $column['type'],
                        'php_type' => $this->mapPhpType($column['type']),
                        'primary_key' => $column['primary'] ? 2 : 1,
                        'required' => $column['nullable'] ? 1 : 2,
                        'insertable' => 2,
                        'editable' => $column['primary'] ? 1 : 2,
                        'show_list' => $column['primary'] ? 1 : 2,
                        'queriable' => 1,
                        'query_type' => 'eq',
                        'view_type' => $this->mapViewType($column['type']),
                        'dict_type' => '',
                        'options' => null,
                        'sort' => $sort++,
                        'override' => 0,
                        'default_value' => $column['default'] ?? '',
                    ];
                }

                $this->columnsService->saveBatch($table->id, $columnData);

                $results[] = [
                    'table_name' => $tableName,
                    'status' => 'success',
                    'message' => '装载成功',
                    'id' => $table->id,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'table_name' => $tableName,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 同步表结构
     * @param int $id 配置ID
     * @return array
     */
    public function sync(int $id): array
    {
        $table = $this->getById($id);

        $dbColumns = $this->getTableColumnsFromDatabase($table->table_name, $table->source);
        $existingColumns = $this->columnsService->getByTableId($id)->keyBy('column_name');

        $newColumns = [];
        $updateColumns = [];

        foreach ($dbColumns as $column) {
            $columnName = $column['name'];

            if (isset($existingColumns[$columnName])) {
                $existing = $existingColumns[$columnName];
                if ($existing->override == 0) {
                    $updateColumns[] = [
                        'id' => $existing->id,
                        'column_comment' => $column['comment'],
                        'column_type' => $column['type'],
                        'php_type' => $this->mapPhpType($column['type']),
                        'required' => $column['nullable'] ? 1 : 2,
                        'view_type' => $this->mapViewType($column['type']),
                        'default_value' => $column['default'] ?? '',
                    ];
                }
            } else {
                $newColumns[] = [
                    'column_name' => $columnName,
                    'column_comment' => $column['comment'],
                    'column_type' => $column['type'],
                    'php_type' => $this->mapPhpType($column['type']),
                    'primary_key' => $column['primary'] ? 2 : 1,
                    'required' => $column['nullable'] ? 1 : 2,
                    'insertable' => 2,
                    'editable' => $column['primary'] ? 1 : 2,
                    'show_list' => $column['primary'] ? 1 : 2,
                    'queriable' => 1,
                    'query_type' => 'eq',
                    'view_type' => $this->mapViewType($column['type']),
                    'dict_type' => '',
                    'options' => null,
                    'sort' => count($newColumns),
                    'override' => 0,
                    'default_value' => $column['default'] ?? '',
                ];
            }
        }

        if (!empty($updateColumns)) {
            $this->columnsService->updateBatch($id, $updateColumns);
        }

        if (!empty($newColumns)) {
            $this->columnsService->saveBatch($id, $newColumns);
        }

        return [
            'new' => count($newColumns),
            'updated' => count($updateColumns),
        ];
    }

    /**
     * 预览代码
     * @param int $id 配置ID
     * @return array
     */
    public function preview(int $id): array
    {
        $table = $this->getById($id);
        return $this->codeEngine->preview($table);
    }

    /**
     * 生成代码（ZIP下载）
     * @param array $ids 配置ID数组
     * @return \support\response\FileResponse
     */
    public function generate(array $ids)
    {
        $tempDir = runtime_path() . '/generate/' . uniqid();

        foreach ($ids as $id) {
            $table = $this->getById($id);
            $this->codeEngine->generateTemp($table, $tempDir);
        }

        $zipFileName = 'code_' . date('YmdHis');

        $response = CodeZip::createZip($tempDir, $zipFileName);

        CodeZip::cleanTemp($tempDir);

        return $response;
    }

    /**
     * 生成代码到项目
     * @param int $id 配置ID
     * @return array
     */
    public function generateFile(int $id): array
    {
        $table = $this->getById($id);

        $tempDir = runtime_path() . '/generate/' . uniqid();

        $this->codeEngine->generateTemp($table, $tempDir);

        $projectRoot = base_path();
        $targetDir = $table->template === 'plugin'
            ? $projectRoot . '/plugin/' . $table->namespace
            : $projectRoot . '/app';

        $className = $table->class_name;

        $files = [];
        $fileMap = [
            $tempDir . '/php/Controller.php' => $targetDir . '/' . $table->namespace . '/' . $className . '/Controller.php',
            $tempDir . '/php/Service.php' => $targetDir . '/' . $table->namespace . '/' . $className . '/Service.php',
            $tempDir . '/php/Model.php' => $targetDir . '/' . $table->namespace . '/' . $className . '/Model.php',
            $tempDir . '/php/Validator.php' => $targetDir . '/' . $table->namespace . '/' . $className . '/Validator.php',
            $tempDir . '/vue/index.vue' => $projectRoot . '/../nanoadmin-vue/src/views/' . $table->namespace . '/' . $className . '/index.vue',
            $tempDir . '/vue/EditDialog.vue' => $projectRoot . '/../nanoadmin-vue/src/views/' . $table->namespace . '/' . $className . '/EditDialog.vue',
            $tempDir . '/vue/TableSearch.vue' => $projectRoot . '/../nanoadmin-vue/src/views/' . $table->namespace . '/' . $className . '/TableSearch.vue',
            $tempDir . '/ts/api.ts' => $projectRoot . '/../nanoadmin-vue/src/api/' . $table->namespace . '/' . $className . 'Api.ts',
        ];

        foreach ($fileMap as $source => $target) {
            if (file_exists($source)) {
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                if (file_exists($target)) {
                    $files[] = [
                        'path' => $target,
                        'status' => 'skipped',
                        'message' => '文件已存在，跳过',
                    ];
                } else {
                    copy($source, $target);
                    $files[] = [
                        'path' => $target,
                        'status' => 'success',
                        'message' => '生成成功',
                    ];
                }
            }
        }

        CodeZip::cleanTemp($tempDir);

        return $files;
    }

    /**
     * 获取字段配置
     * @param int $tableId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTableColumns(int $tableId)
    {
        $this->getById($tableId);
        return $this->columnsService->getByTableId($tableId);
    }

    /**
     * 获取数据库表列表
     * @param string $source 数据源
     * @return array
     */
    public function getDatabaseTables(string $source = ''): array
    {
        $prefix = config('database.connections.mysql.prefix', '');
        $tables = Db::select("SHOW TABLE STATUS WHERE Name NOT LIKE '{$prefix}%' AND Name NOT LIKE '%_view'");

        $result = [];
        foreach ($tables as $table) {
            $result[] = [
                'name' => $table->Name,
                'comment' => $table->Comment ?? '',
                'engine' => $table->Engine ?? '',
                'collation' => $table->Collation ?? '',
                'rows' => $table->Rows ?? 0,
            ];
        }

        return $result;
    }

    /**
     * 保存字段配置
     * @param int $tableId
     * @param array $columns
     * @return bool
     */
    public function saveColumns(int $tableId, array $columns): bool
    {
        $this->getById($tableId);

        $this->columnsService->deleteByTableId($tableId);

        if (!empty($columns)) {
            $this->columnsService->saveBatch($tableId, $columns);
        }

        return true;
    }

    /**
     * 从数据库获取表字段信息
     * @param string $tableName
     * @param string $source
     * @return array
     */
    private function getTableColumnsFromDatabase(string $tableName, string $source = ''): array
    {
        $columns = Db::select("SHOW FULL COLUMNS FROM `{$tableName}`");

        $result = [];
        foreach ($columns as $column) {
            $result[] = [
                'name' => $column->Field,
                'type' => $column->Type,
                'comment' => $column->Comment ?? '',
                'nullable' => $column->Null === 'YES',
                'primary' => strtolower($column->Key) === 'pri',
                'default' => $column->Default,
                'extra' => $column->Extra ?? '',
            ];
        }

        return $result;
    }

    /**
     * 从数据库获取表注释
     * @param string $tableName
     * @param string $source
     * @return string
     */
    private function getTableCommentFromDatabase(string $tableName, string $source = ''): string
    {
        $table = Db::selectOne("SHOW TABLE STATUS WHERE Name = '{$tableName}'");
        return $table ? ($table->Comment ?? '') : '';
    }

    /**
     * 根据表名生成类名
     * @param string $tableName
     * @return string
     */
    private function generateClassName(string $tableName): string
    {
        $tableName = preg_replace('/^' . preg_quote(config('database.connections.mysql.prefix', ''), '/') . '/', '', $tableName);

        $parts = explode('_', $tableName);
        $className = '';

        foreach ($parts as $part) {
            $className .= ucfirst(strtolower($part));
        }

        return $className ?: 'Unknown';
    }

    /**
     * 映射数据库类型到PHP类型
     * @param string $dbType
     * @return string
     */
    private function mapPhpType(string $dbType): string
    {
        $dbType = strtolower($dbType);

        if (str_starts_with($dbType, 'int') || str_starts_with($dbType, 'tinyint') || str_starts_with($dbType, 'smallint') || str_starts_with($dbType, 'mediumint') || str_starts_with($dbType, 'bigint')) {
            return 'int';
        }

        if (str_starts_with($dbType, 'decimal') || str_starts_with($dbType, 'float') || str_starts_with($dbType, 'double') || str_starts_with($dbType, 'real')) {
            return 'float';
        }

        if (str_starts_with($dbType, 'datetime') || str_starts_with($dbType, 'timestamp')) {
            return 'string';
        }

        if (str_starts_with($dbType, 'date') || str_starts_with($dbType, 'time')) {
            return 'string';
        }

        return 'string';
    }

    /**
     * 映射数据库类型到视图类型
     * @param string $dbType
     * @return string
     */
    private function mapViewType(string $dbType): string
    {
        $dbType = strtolower($dbType);

        if (str_starts_with($dbType, 'text') || str_starts_with($dbType, 'varchar') || str_starts_with($dbType, 'char')) {
            if (str_contains($dbType, '(255)') || str_contains($dbType, '(100)') || str_contains($dbType, '(50)')) {
                return 'input';
            }
            return 'textarea';
        }

        if (str_starts_with($dbType, 'int') || str_starts_with($dbType, 'tinyint') || str_starts_with($dbType, 'smallint') || str_starts_with($dbType, 'mediumint') || str_starts_with($dbType, 'bigint')) {
            return 'input';
        }

        if (str_starts_with($dbType, 'decimal') || str_starts_with($dbType, 'float') || str_starts_with($dbType, 'double')) {
            return 'input';
        }

        if (str_starts_with($dbType, 'datetime') || str_starts_with($dbType, 'timestamp')) {
            return 'datetime';
        }

        if (str_starts_with($dbType, 'date')) {
            return 'date';
        }

        if (str_starts_with($dbType, 'enum') || str_starts_with($dbType, 'set')) {
            return 'select';
        }

        if (str_starts_with($dbType, 'decimal') || str_starts_with($dbType, 'float')) {
            return 'input';
        }

        return 'input';
    }
}
