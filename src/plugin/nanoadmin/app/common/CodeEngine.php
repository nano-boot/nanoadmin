<?php

namespace plugin\nanoadmin\app\common;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use plugin\nanoadmin\app\model\tool\GenerateTables;
use plugin\nanoadmin\app\model\tool\GenerateColumns;
use support\Container;

/**
 * 代码引擎 - 基于 Twig 模板引擎渲染代码
 */
class CodeEngine
{
    /**
     * Twig 环境
     * @var Environment
     */
    private Environment $twig;

    /**
     * 模板目录
     * @var string
     */
    private string $stubPath;

    /**
     * 构造方法
     */
    public function __construct()
    {
        $this->stubPath = dirname(__DIR__, 2) . '/stub';
        $loader = new FilesystemLoader($this->stubPath);
        $this->twig = new Environment($loader, [
            'debug' => false,
            'strict_variables' => true,
            'autoescape' => false,
            'cache' => false,
        ]);
    }

    /**
     * 渲染内容
     * @param string $template 模板路径（相对于 stub 目录）
     * @param array $data 渲染数据
     * @return string
     */
    public function renderContent(string $template, array $data): string
    {
        return $this->twig->render($template, $data);
    }

    /**
     * 准备渲染数据
     * @param GenerateTables $table
     * @return array
     */
    public function prepareRenderData(GenerateTables $table): array
    {
        $columns = $table->columns()->orderBy('sort', 'asc')->get()->toArray();

        $primaryColumn = null;
        $listColumns = [];
        $formColumns = [];
        $queryColumns = [];

        foreach ($columns as $column) {
            if ($column['primary_key'] == 2) {
                $primaryColumn = $column;
            }
            if ($column['show_list'] == 2) {
                $listColumns[] = $column;
            }
            if ($column['insertable'] == 2 || $column['editable'] == 2) {
                $formColumns[] = $column;
            }
            if ($column['queriable'] == 2) {
                $queryColumns[] = $column;
            }
        }

        $options = $table->options ?? [];

        return [
            'table' => $table->toArray(),
            'columns' => $columns,
            'primaryColumn' => $primaryColumn,
            'listColumns' => $listColumns,
            'formColumns' => $formColumns,
            'queryColumns' => $queryColumns,
            'options' => $options,
            'namespace' => $table->namespace,
            'packageName' => $table->package_name,
            'className' => $table->class_name,
            'businessName' => $table->business_name,
            'tableName' => $table->table_name,
            'tableComment' => $table->table_comment,
            'template' => $table->template,
            'tplCategory' => $table->tpl_category,
            'menuName' => $table->menu_name,
            'generateMenus' => explode(',', $table->generate_menus),
            'primaryKey' => $primaryColumn['column_name'] ?? 'id',
            'primaryKeyConvert' => $this->convertToCamelCase($primaryColumn['column_name'] ?? 'id'),
            'lowerClassName' => lcfirst($table->class_name),
            'upperClassName' => ucfirst($table->class_name),
            'date' => date('Y-m-d H:i:s'),
            'author' => 'NanoAdmin',
        ];
    }

    /**
     * 生成后端代码
     * @param GenerateTables $table
     * @return array ['controller' => string, 'service' => string, 'model' => string, 'validator' => string]
     */
    public function generateBackend(GenerateTables $table): array
    {
        $data = $this->prepareRenderData($table);

        return [
            'controller' => $this->renderContent('php/controller.stub', $data),
            'service' => $this->renderContent('php/service.stub', $data),
            'model' => $this->renderContent('php/model.stub', $data),
            'validator' => $this->renderContent('php/validator.stub', $data),
        ];
    }

    /**
     * 生成前端代码
     * @param GenerateTables $table
     * @return array ['index' => string, 'editDialog' => string, 'tableSearch' => string, 'api' => string]
     */
    public function generateFrontend(GenerateTables $table): array
    {
        $data = $this->prepareRenderData($table);

        return [
            'index' => $this->renderContent('vue/index.stub', $data),
            'editDialog' => $this->renderContent('vue/edit-dialog.stub', $data),
            'tableSearch' => $this->renderContent('vue/table-search.stub', $data),
            'api' => $this->renderContent('ts/api.stub', $data),
        ];
    }

    /**
     * 生成 SQL 代码
     * @param GenerateTables $table
     * @return array ['menu' => string]
     */
    public function generateSql(GenerateTables $table): array
    {
        $data = $this->prepareRenderData($table);

        return [
            'menu' => $this->renderContent('sql/menu.stub', $data),
        ];
    }

    /**
     * 预览代码（返回所有模板渲染结果）
     * @param GenerateTables $table
     * @return array
     */
    public function preview(GenerateTables $table): array
    {
        $backend = $this->generateBackend($table);
        $frontend = $this->generateFrontend($table);
        $sql = $this->generateSql($table);

        return [
            [
                'tab_name' => 'PHP',
                'name' => 'Controller',
                'lang' => 'php',
                'code' => $backend['controller'],
            ],
            [
                'tab_name' => 'PHP',
                'name' => 'Service',
                'lang' => 'php',
                'code' => $backend['service'],
            ],
            [
                'tab_name' => 'PHP',
                'name' => 'Model',
                'lang' => 'php',
                'code' => $backend['model'],
            ],
            [
                'tab_name' => 'PHP',
                'name' => 'Validator',
                'lang' => 'php',
                'code' => $backend['validator'],
            ],
            [
                'tab_name' => 'Vue',
                'name' => 'Index',
                'lang' => 'xml',
                'code' => $frontend['index'],
            ],
            [
                'tab_name' => 'Vue',
                'name' => 'EditDialog',
                'lang' => 'xml',
                'code' => $frontend['editDialog'],
            ],
            [
                'tab_name' => 'Vue',
                'name' => 'TableSearch',
                'lang' => 'xml',
                'code' => $frontend['tableSearch'],
            ],
            [
                'tab_name' => 'TypeScript',
                'name' => 'API',
                'lang' => 'typescript',
                'code' => $frontend['api'],
            ],
            [
                'tab_name' => 'SQL',
                'name' => 'Menu',
                'lang' => 'sql',
                'code' => $sql['menu'],
            ],
        ];
    }

    /**
     * 生成临时目录中的代码文件
     * @param GenerateTables $table
     * @param string $tempDir 临时目录
     * @return array 生成的每个文件的路径和内容
     */
    public function generateTemp(GenerateTables $table, string $tempDir): array
    {
        $backend = $this->generateBackend($table);
        $frontend = $this->generateFrontend($table);
        $sql = $this->generateSql($table);

        $files = [];
        $namespace = $table->namespace;
        $className = $table->class_name;

        if ($table->template === 'plugin') {
            $basePath = $tempDir . '/plugin/' . $namespace . '/' . $className;
        } else {
            $basePath = $tempDir . '/app/' . $namespace . '/' . $className;
        }

        $backendPath = $basePath . '/php';
        $frontendPath = $basePath . '/vue';
        $tsPath = $basePath . '/ts';
        $sqlPath = $basePath . '/sql';

        foreach ([$backendPath, $frontendPath, $tsPath, $sqlPath] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $fileMap = [
            $backendPath . '/Controller.php' => $backend['controller'],
            $backendPath . '/Service.php' => $backend['service'],
            $backendPath . '/Model.php' => $backend['model'],
            $backendPath . '/Validator.php' => $backend['validator'],
            $frontendPath . '/index.vue' => $frontend['index'],
            $frontendPath . '/EditDialog.vue' => $frontend['editDialog'],
            $frontendPath . '/TableSearch.vue' => $frontend['tableSearch'],
            $tsPath . '/api.ts' => $frontend['api'],
            $sqlPath . '/menu.sql' => $sql['menu'],
        ];

        foreach ($fileMap as $path => $content) {
            file_put_contents($path, $content);
            $files[] = [
                'path' => $path,
                'name' => basename($path),
                'type' => pathinfo($path, PATHINFO_EXTENSION),
            ];
        }

        return $files;
    }

    /**
     * 驼峰转蛇形
     * @param string $str
     * @return string
     */
    private function convertToCamelCase(string $str): string
    {
        return strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $str));
    }
}
