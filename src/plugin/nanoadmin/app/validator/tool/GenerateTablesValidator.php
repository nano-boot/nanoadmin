<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator\tool;

use plugin\nanoadmin\app\model\tool\GenerateTables;
use plugin\nanoadmin\app\validator\ValidatorBase;
use support\validation\Rule;

/**
 * 代码生成验证器
 */
class GenerateTablesValidator extends ValidatorBase
{
    protected ?string $model = GenerateTables::class;
    protected string $primaryKey = 'id';

    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'gt:0',
            ],
            'table_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
            'table_comment' => [
                'nullable',
                'string',
                'max:500',
            ],
            'class_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Z][a-zA-Z0-9]*$/',
            ],
            'business_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'namespace' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
            'package_name' => [
                'required',
                'string',
                'max:100',
            ],
            'template' => [
                'nullable',
                'string',
                Rule::in(['plugin', 'app']),
            ],
            'tpl_category' => [
                'nullable',
                'string',
                Rule::in(['single', 'tree']),
            ],
            'menu_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'belong_menu_id' => [
                'nullable',
                'integer',
                'gte:0',
            ],
            'generate_menus' => [
                'nullable',
                'string',
                'max:255',
            ],
            'options' => [
                'nullable',
                'array',
            ],
            'source' => [
                'nullable',
                'string',
                'max:50',
            ],
            'names' => [
                'required',
                'array',
                'min:1',
            ],
            'names.*' => [
                'required',
                'string',
                'max:100',
            ],
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'required',
                'integer',
                'gt:0',
            ],
            'table_id' => [
                'required',
                'integer',
                'gt:0',
            ],
            'columns' => [
                'nullable',
                'array',
            ],
            'columns.*.column_name' => [
                'required',
                'string',
                'max:100',
            ],
            'columns.*.column_comment' => [
                'nullable',
                'string',
                'max:500',
            ],
            'columns.*.column_type' => [
                'required',
                'string',
                'max:50',
            ],
            'columns.*.php_type' => [
                'nullable',
                'string',
                'max:50',
            ],
            'columns.*.view_type' => [
                'nullable',
                'string',
                'max:50',
            ],
            'columns.*.query_type' => [
                'nullable',
                'string',
                'max:20',
            ],
            'columns.*.sort' => [
                'nullable',
                'integer',
            ],
            'columns.*.primary_key' => [
                'nullable',
                'integer',
                Rule::in([1, 2]),
            ],
            'columns.*.required' => [
                'nullable',
                'integer',
                Rule::in([1, 2]),
            ],
            'columns.*.insertable' => [
                'nullable',
                'integer',
                Rule::in([1, 2]),
            ],
            'columns.*.editable' => [
                'nullable',
                'integer',
                Rule::in([1, 2]),
            ],
            'columns.*.show_list' => [
                'nullable',
                'integer',
                Rule::in([1, 2]),
            ],
            'columns.*.queriable' => [
                'nullable',
                'integer',
                Rule::in([1, 2]),
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'ID不能为空',
            'id.integer' => 'ID必须是整数',
            'id.gt' => 'ID必须大于0',

            'table_name.required' => '表名称不能为空',
            'table_name.max' => '表名称不能超过100个字符',
            'table_name.regex' => '表名称必须以小写字母开头，只能包含小写字母、数字和下划线',

            'table_comment.max' => '表描述不能超过500个字符',

            'class_name.required' => '类名称不能为空',
            'class_name.max' => '类名称不能超过100个字符',
            'class_name.regex' => '类名称必须以大写字母开头，只能包含字母和数字',

            'business_name.max' => '业务名称不能超过100个字符',

            'namespace.required' => '命名空间不能为空',
            'namespace.max' => '命名空间不能超过100个字符',
            'namespace.regex' => '命名空间必须以小写字母开头，只能包含小写字母、数字和下划线',

            'package_name.required' => '包名称不能为空',
            'package_name.max' => '包名称不能超过100个字符',

            'template.in' => '应用类型只能是 plugin 或 app',

            'tpl_category.in' => '生成类型只能是 single 或 tree',

            'menu_name.max' => '菜单名称不能超过100个字符',

            'belong_menu_id.integer' => '所属菜单ID必须是整数',
            'belong_menu_id.gte' => '所属菜单ID必须大于等于0',

            'generate_menus.max' => '生成菜单不能超过255个字符',

            'options.array' => '扩展选项必须是数组',

            'source.max' => '数据源不能超过50个字符',

            'names.required' => '表名数组不能为空',
            'names.array' => '表名必须是数组',
            'names.min' => '至少选择一个表',
            'names.*.required' => '表名不能为空',
            'names.*.string' => '表名必须是字符串',
            'names.*.max' => '表名不能超过100个字符',

            'ids.required' => 'ID数组不能为空',
            'ids.array' => 'ID必须是数组',
            'ids.min' => '至少选择一个项目',
            'ids.*.required' => 'ID不能为空',
            'ids.*.integer' => 'ID必须是整数',
            'ids.*.gt' => 'ID必须大于0',

            'table_id.required' => '表ID不能为空',
            'table_id.integer' => '表ID必须是整数',
            'table_id.gt' => '表ID必须大于0',

            'page.integer' => '页码必须是整数',
            'page.min' => '页码必须大于0',

            'limit.integer' => '每页数量必须是整数',
            'limit.min' => '每页数量必须大于0',
            'limit.max' => '每页数量不能超过100',
        ];
    }

    public function scenes(): array
    {
        return [
            'page' => ['page', 'limit', 'table_name', 'table_comment', 'namespace', 'keyword'],
            'show' => ['id'],
            'update' => [
                'id',
                'table_name',
                'table_comment',
                'class_name',
                'business_name',
                'namespace',
                'package_name',
                'template',
                'tpl_category',
                'menu_name',
                'belong_menu_id',
                'generate_menus',
                'options',
            ],
            'destroy' => ['id'],
            'loadTable' => ['names', 'source'],
            'sync' => ['id'],
            'preview' => ['id'],
            'generate' => ['ids'],
            'generateFile' => ['id'],
            'getTableColumns' => ['table_id'],
            'getDatabaseTables' => ['source'],
            'saveColumns' => ['table_id', 'columns'],
        ];
    }
}
