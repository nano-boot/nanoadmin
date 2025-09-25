<?php
declare(strict_types=1);

namespace plugin\theadmin\app\validator;

use plugin\theadmin\app\common\ApiException;
use plugin\theadmin\app\common\Code;
use plugin\theadmin\app\model\Admin;
use think\Validate;
use support\Request;

/**
 * 表单请求验证基类
 *
 * @method string get(string $name, mixed $default = null) 获取GET参数
 * @method string post(string $name, mixed $default = null) 获取POST参数
 * @method mixed input(string $name, mixed $default = null) 获取输入参数
 * @method array all() 获取所有参数
 * @method string method() 获取请求方法
 * @method string path() 获取请求路径
 * @method string url() 获取请求URL
 * @method string userAgent() 获取用户代理
 * @method mixed header(string $key, mixed $default = null) 获取请求头
 * @method bool isGet() 是否GET请求
 * @method bool isDelete() 是否DELETE请求
 * @method bool isAjax() 是否AJAX请求
 * @method bool isJson() 是否JSON请求
 * @method bool has(string $key) 是否存在参数
 * @method mixed file(string $key) 获取上传文件
 * @method array files() 获取所有上传文件
 * @property Admin|null $admin 当前登录管理员
 * @property int|null $adminId 当前登录管理员ID
 * @property array|null $tokenPayload JWT Token 载荷数据
 */
class ValidatorBase extends Validate
{
    /**
     * 当前请求实例
     */
    protected $request;

    public function __construct() {
        parent::__construct();
        $this->request = request();
        if ($this->scene){
            $scene = request()->action;
            if (!$this->hasScene($scene)){
                return;
            }
            $this->scene($scene);
        }
        $this->failException()->check(request()->all() + request()->route->param());
    }

    /**
     * 获取请求实例
     */
    public function getRequest(): Request|\Webman\Http\Request|null
    {
        return $this->request;
    }

    /**
     * 检查是否存在参数
     */
    public function hasParam(string $key): bool
    {
        return isset($this->request->all()[$key]);
    }

    /**
     * 获取客户端IP
     */
    public function getIp(): string
    {
        return $this->request->getRealIp();
    }

    /**
     * 是否POST请求
     */
    public function isPost(): bool
    {
        return $this->request->method() === 'POST';
    }

    /**
     * 是否PUT请求
     */
    public function isPut(): bool
    {
        return $this->request->method() === 'PUT';
    }


    /**
     * 手动验证数据
     * 
     * @param array $data 要验证的数据
     * @param string|null $scene 验证场景
     * @return array 验证后的数据
     * @throws ApiException
     */
    public function validateData(array $data, ?string $scene = null): array
    {
        try {
            // 设置验证场景
            if ($scene && $this->hasScene($scene)) {
                $this->scene($scene);
            }

            // 执行验证
            if (!$this->check($data)) {
                $errors = $this->getError();
                if (is_array($errors)) {
                    throw new ApiException(Code::PARAMETER_ERROR, implode('; ', $errors));
                } else {
                    throw new ApiException(Code::PARAMETER_ERROR, $errors);
                }
            }

            return $data;
        } catch (\Exception $e) {
            if ($e instanceof ApiException) {
                throw $e;
            }
            throw new ApiException(Code::PARAMETER_ERROR, '数据验证失败: ' . $e->getMessage());
        }
    }

    /**
     * 代理请求对象的其他方法
     * @throws \Exception
     */
   public function __call($method, $args) {
       if (method_exists($this->request, $method)) {
           return call_user_func_array([$this->request, $method], $args);
       }
       throw new \Exception("Method $method does not exist");
   }

    /**
     * 验证数据唯一性
     *
     * @param mixed $value 字段值
     * @param string|array $rule 验证规则，格式：table,field,except,idColumn
     * @param array $data 验证数据
     * @param string $field 验证字段名
     * @return bool
     * @throws \Exception
     */
    public function unique($value, $rule, array $data = [], string $field = ''): bool
    {
        if (empty($value)) {
            return true;
        }

        if (is_string($rule)) {
            $rule = explode(',', $rule);
        }

        if (class_exists($rule[0])) {
            // 指定模型类
            $query = new $rule[0]();
        } else {
            $query = \support\Db::table($rule[0]);
        }

        $key = $rule[1] ?? $field;
        $conditions = [];

        if (str_contains($key, '^')) {
            // 支持多个字段验证
            $fields = explode('^', $key);
            foreach ($fields as $fieldName) {
                if (isset($data[$fieldName])) {
                    $conditions[] = [$fieldName, '=', $data[$fieldName]];
                }
            }
        } elseif (str_contains($key, '=')) {
            // 支持复杂验证 (如: status=1&type=admin)
            parse_str($key, $array);
            foreach ($array as $k => $val) {
                $conditions[] = [$k, '=', $data[$k] ?? $val];
            }
        } elseif (isset($data[$field])) {
            // 普通字段验证
            $conditions[] = [$key, '=', $data[$field]];
        } else {
            // 直接使用传入的值
            $conditions[] = [$key, '=', $value];
        }

        // 获取主键字段
        if (str_contains($rule[0], '\\')) {
            // 模型类获取主键
            $pk = !empty($rule[3]) ? $rule[3] : $query->getKeyName();
        } else {
            // 表名默认主键为 id
            $pk = !empty($rule[3]) ? $rule[3] : 'id';
        }

        // 处理排除条件
        if (isset($rule[2])) {
            $conditions[] = [$pk, '<>', $rule[2]];
        } elseif (isset($data[$pk])) {
            $conditions[] = [$pk, '<>', $data[$pk]];
        }

        try {
            // 构建查询条件 - 使用展开操作符
            foreach ($conditions as $condition) {
                $query = $query->where(...$condition);
            }

            if (str_contains($rule[0], '\\')) {
                $exists = $query->exists();
            } else {
                $exists = $query->count() > 0;
            }

            return !$exists;

        } catch (\Exception $e) {
            throw new \Exception('验证唯一性时发生错误: ' . $e->getMessage());
        }
    }
}