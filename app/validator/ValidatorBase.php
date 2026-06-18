<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator;

use plugin\nanoadmin\app\model\Admin;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;
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
    protected Request $supportRequest;

    public function __construct($auto = false) {
        parent::__construct();
        $this->supportRequest = request();
        if($auto){
            // 自动设置场景
            if ($this->scene){
                // 智能获取场景名
                $scene = $this->getSceneName();

                if (!$this->hasScene($scene)){
                    return;
                }
                $this->scene($scene);
            }
            // 执行验证
            $this->failException()->check(request()->all() + request()->route->param());
        }
    }

    /**
     * 智能获取场景名
     *
     * 推断顺序：
     *   1. request()->action      框架约定（最优先）
     *   2. 路由回调里的方法名     Workerman 路由 [Controller, method] 形态
     *   3. HTTP 方法 + 路径       标准 REST 推断
     *
     * 所有返回值都经过 isValidSceneName() 防御性校验，
     * 避免 Closure / null / 空串 / 非标识符 等异常值流入 think-orm。
     *
     * @return string|null 无法识别时返回 null（hasScene(null) === false，构造方法会静默跳过）
     */
    protected function getSceneName(): ?string
    {
        if ($scene = $this->sceneFromRequestAction()) {
            return $scene;
        }

        if ($scene = $this->sceneFromRouteCallback()) {
            return $scene;
        }

        return $this->sceneFromHttpMethod();
    }

    /**
     * 方法 1：从 request()->action 推断
     * 适用框架：think-orm/Laravel 等将控制器方法名写入 request()->action 的栈
     */
    private function sceneFromRequestAction(): ?string
    {
        $action = $this->supportRequest->action ?? null;
        return $this->isValidSceneName($action) ? $action : null;
    }

    /**
     * 方法 2：从路由回调 [Controller::class, 'methodName'] 推断
     * 适用框架：Workerman Route（项目当前所用）
     */
    private function sceneFromRouteCallback(): ?string
    {
        $route = $this->supportRequest->route ?? null;
        if (!$route || !method_exists($route, 'getCallback')) {
            return null;
        }
        $callback = $route->getCallback();
        if (!is_array($callback) || !isset($callback[1])) {
            return null;
        }
        return $this->isValidSceneName($callback[1]) ? $callback[1] : null;
    }

    /**
     * 方法 3：基于 HTTP 方法 + 路径数字段推断
     * 仅覆盖标准 REST 5 场景（store/update/destroy/show/index），
     * 其它路径（含数字的 POST、batch_* 等）返回 null。
     */
    private function sceneFromHttpMethod(): ?string
    {
        $method = strtoupper((string)$this->supportRequest->method());
        $path   = (string)$this->supportRequest->path();
        $hasId  = (bool)preg_match('/\/\d+/', $path);

        return match (true) {
            $method === 'POST'   && !$hasId => 'store',
            $method === 'PUT'    &&  $hasId => 'update',
            $method === 'DELETE' &&  $hasId => 'destroy',
            $method === 'GET'    &&  $hasId => 'show',
            $method === 'GET'               => 'index',
            default                         => null,
        };
    }

    /**
     * 防御性校验：合法的场景名应当是合法 PHP 标识符
     *  - 必须是字符串
     *  - 非空
     *  - 匹配 /^[a-zA-Z_][a-zA-Z0-9_]*$/
     */
    private function isValidSceneName(mixed $name): bool
    {
        return is_string($name)
            && $name !== ''
            && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) === 1;
    }

    /**
     * 获取请求实例
     */
    public function getRequest(): Request
    {
        return $this->supportRequest;
    }

    /**
     * 检查是否存在参数
     */
    public function hasParam(string $key): bool
    {
        return isset($this->supportRequest->all()[$key]);
    }

    /**
     * 获取客户端IP
     */
    public function getIp(): string
    {
        return $this->supportRequest->getRealIp();
    }

    /**
     * 是否POST请求
     */
    public function isPost(): bool
    {
        return $this->supportRequest->method() === 'POST';
    }

    /**
     * 是否PUT请求
     */
    public function isPut(): bool
    {
        return $this->supportRequest->method() === 'PUT';
    }


    /**
     * 代理请求对象的其他方法
     * @throws \Exception
     */
   public function __call($method, $args) {
       if (method_exists($this->supportRequest, $method)) {
           return call_user_func_array([$this->supportRequest, $method], $args);
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

    
    /**
     * 获取经过验证的请求数据
     *
     * @param string $scene 验证场景
     * @return array
     */
    public function validated(?string $scene = null): array
    {
        if(!$scene){
            $scene = $this->getSceneName();
        }
        return $this->validateData($this->all(), $scene);
    }

    /**
     * 验证数据
     *
     * @param array $data 要验证的数据
     * @param string|null $scene 验证场景
     * @return array 验证通过的数据
     * @throws ApiException
     */
    public function validateData(array $data, ?string $scene = null): array
    {
        try {
            // 设置验证场景
            if ($scene) {
                $this->scene($scene);
            }

            // 执行验证并返回验证后的数据
            return $this->checked($data);
        } catch (\think\exception\ValidateException $e) {
            // 将验证异常转换为API异常
            $error = $e->getError();
            $message = is_array($error) ? implode(', ', $error) : $error;
            throw new ApiException(
                Code::VALIDATION_ERROR,
                $message
            );
        }
    }
    
}