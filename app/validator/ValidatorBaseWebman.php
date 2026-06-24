<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator;

use support\validation\Rule as IlluminateRule;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;

/**
 * 基于 webman/validation（support\validation\Validator）的表单验证器基类
 *
 * 特性：
 * - 自动生成 sceneXxx() 方法（通过 __call 拦截）
 * - 统一 unique 规则处理（基于路由参数或上下文）
 * - 通用辅助方法（validateId, validateBatchIds 等）
 *
 * 继承 support\validation\Validator，提供：
 * - make() 静态工厂方法
 * - withScene() 场景方法
 * - validate() / fails() / errors() 验证方法
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
abstract class ValidatorBaseWebman
{


    // ═══════════════════════════════════════════════════════════════
    // 子类配置属性
    // ═══════════════════════════════════════════════════════════════

    /**
     * 验证规则（由子类通过 rules() 方法提供）
     *
     * @var array<string, array<string>|string>
     */
    protected array $rules = [];

    /**
     * 场景定义
     *
     * @var array<string, array<string>>
     */
    protected array $sceneDefinitions = [];

    /**
     * 自定义消息
     *
     * @var array<string, string>
     */
    protected array $messages = [];

    /**
     * 自定义属性名
     *
     * @var array<string, string>
     */
    protected array $attributes = [];

    /**
     * 模型类（用于 unique/exists 规则自动解析表名）
     *
     * @var string|null
     */
    protected ?string $model = null;

    /**
     * 主键字段
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    // ═══════════════════════════════════════════════════════════════
    // 内部状态属性
    // ═══════════════════════════════════════════════════════════════

    /** @var array 当前待校验数据 */
    protected array $_data = [];
    /** @var string|null 当前绑定场景名 */
    protected ?string $_scene = null;
    /** @var array 上下文（excludeId 等） */
    protected array $_context = [];
    /** @var bool 快速失败模式 */
    protected bool $stopOnFirstFailure = true;

    // ═══════════════════════════════════════════════════════════════
    // 核心方法
    // ═══════════════════════════════════════════════════════════════

    /**
     * 获取验证规则
     */
    public function rules(): array
    {
        $scene = $this->_scene;
        $allRules = $this->getAllRules();

        if ($scene === null) {
            return $allRules;
        }

        return $this->getRulesByScene($scene, $allRules);
    }

    /**
     * 获取全部验证规则
     *
     * @return array<string, array<string>|string>
     */
    private function getAllRules(): array
    {
        $class = static::class;

        try {
            $reflection = new \ReflectionMethod($class, 'rules');
            if ($reflection->getDeclaringClass()->getName() === $class) {
                $tempValidator = new $class();
                return $reflection->invoke($tempValidator);
            }
        } catch (\Throwable) {
            // 忽略
        }

        return $this->rules;
    }

    /**
     * 根据场景获取规则
     */
    private function getRulesByScene(string $scene, array $allRules): array
    {
        $class = static::class;
        try {
            $reflection = new \ReflectionMethod($class, 'scenes');
            if ($reflection->getDeclaringClass()->getName() === $class) {
                $tempValidator = new $class();
                $sceneDefs = $reflection->invoke($tempValidator);
            } else {
                $sceneDefs = $this->sceneDefinitions;
            }
        } catch (\Throwable) {
            $sceneDefs = $this->sceneDefinitions;
        }

        if (!isset($sceneDefs[$scene])) {
            return $allRules;
        }

        $sceneFields = $sceneDefs[$scene];

        return array_intersect_key($allRules, array_flip($sceneFields));
    }

    /**
     * 获取场景定义
     *
     * 优先使用子类的 scenes() 方法（如果被覆盖），否则使用 $sceneDefinitions 属性
     *
     * @return array<string, array<string>>
     */
    public function getScenes(): array
    {
        $class = static::class;

        try {
            $reflection = new \ReflectionMethod($class, 'scenes');
            if ($reflection->getDeclaringClass()->getName() === $class) {
                // 子类覆盖了 scenes() 方法
                $tempValidator = new $class();
                return $reflection->invoke($tempValidator);
            }
        } catch (\Throwable) {
            // 忽略
        }

        return $this->sceneDefinitions;
    }

    /**
     * 获取自定义消息
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * 获取自定义属性名
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    // ═══════════════════════════════════════════════════════════════
    // unique/exists 规则辅助方法
    // ═══════════════════════════════════════════════════════════════

    /**
     * 获取唯一性排除ID
     *
     * 优先级：
     * 1. 上下文中的 excludeId
     * 2. 路由参数中的 id
     *
     * @return int|null
     */
    protected function getUniqueIgnoreId(): ?int
    {
        // 1. 优先从上下文获取
        if (!empty($this->_context['excludeId'])) {
            $id = (int)$this->_context['excludeId'];
            return $id > 0 ? $id : null;
        }

        // 2. 从路由参数获取
        $routeId = request()->route->param('id');
        if ($routeId !== null && (int)$routeId > 0) {
            return (int)$routeId;
        }

        return null;
    }

    /**
     * 构建 unique 规则（自动处理排除自身）
     *
     * @param string $column 字段名
     * @param string|null $table 表名（从 $model 自动推断）
     * @return \Illuminate\Validation\Rules\Unique
     */
    protected function unique(string $column, ?string $table = null): \Illuminate\Validation\Rules\Unique
    {
        if ($table === null && $this->model) {
            $modelInstance = new $this->model();
            $table = $modelInstance->getTable();
        }

        $rule = IlluminateRule::unique($table, $column);
        $ignoreId = $this->getUniqueIgnoreId();

        if ($ignoreId !== null) {
            $rule = $rule->ignore($ignoreId, $this->primaryKey);
        }

        return $rule;
    }

    /**
     * 生成 exists 规则
     *
     * @param string $table
     * @param string $column
     * @return \Illuminate\Validation\Rules\Exists
     */
    protected static function exists(string $table, string $column): \Illuminate\Validation\Rules\Exists
    {
        return IlluminateRule::exists($table, $column);
    }

    // ═══════════════════════════════════════════════════════════════
    // 通用验证方法
    // ═══════════════════════════════════════════════════════════════

    /**
     * 验证数据（核心方法）
     *
     * @param array $data 要验证的数据
     * @param string|null $scene 验证场景
     * @param array $context 额外的上下文数据
     * @return array
     * @throws ApiException
     */
    public function validateData(array $data, ?string $scene = null, array $context = []): array
    {
        $rules = $this->getSceneRules($scene);
        $rules = $this->resolveContextPlaceholders($rules, $context);

        $factory = \Webman\Validation\Factory\ValidationFactory::getFactory();
        $validator = $factory->make($data, $rules, $this->messages(), $this->attributes());

        if ($this->stopOnFirstFailure) {
            $validator->stopOnFirstFailure();
        }

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $message = implode('; ', $errors);
            throw new ApiException(Code::VALIDATION_ERROR->value, $message);
        }

        return $validator->validated();
    }

    /**
     * 注入上下文
     */
    public function withContext(array $context): static
    {
        $this->_context = array_merge($this->_context, $context);
        return $this;
    }

    /**
     * 捕获 POST 数据
     */
    public function setPost(): static
    {
        $this->_data = request()->post();
        return $this;
    }

    /**
     * 捕获 GET 数据
     */
    public function setGet(): static
    {
        $this->_data = request()->get();
        return $this;
    }

    /**
     * 自定义数据源
     */
    public function setData(array $data): static
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * 魔术方法：自动生成 sceneXxx() 方法
     *
     * 场景方法统一由基类自动生成，子类无需再定义。
     * 例如：scenes() 返回 ['create' => [...]]，则自动生成 sceneCreate() 方法。
     */
    public function __call(string $name, array $args): static
    {
        if (str_starts_with($name, 'scene')) {
            $scene = lcfirst(substr($name, 5));
            return $this->bindScene($scene);
        }

        throw new \BadMethodCallException("方法 {$name} 不存在");
    }

    /**
     * 设置验证场景
     *
     * @param string $name 场景名称
     * @return $this
     * @throws \InvalidArgumentException
     *
     * @example
     * $data = $validator->scene('update')->setPost()->check();
     * // 或使用魔术方法：$validator->sceneUpdate()->setPost()->check();
     */
    public function scene(string $name): static
    {
        return $this->bindScene($name);
    }

    /**
     * 绑定场景
     */
    public function bindScene(string $scene): static
    {
        $scenes = $this->getScenes();
        if (!isset($scenes[$scene])) {
            throw new \InvalidArgumentException("场景 '{$scene}' 未定义");
        }

        $this->_scene = $scene;

        return $this;
    }

    /**
     * 执行校验
     *
     * @param array|null $data 要验证的数据，默认为空（使用请求数据或 setData 设置的数据）
     * @return array
     * @throws ApiException
     *
     * 使用示例：
     * ```php
     * // 方式1：使用请求数据
     * $data = $validator->scene('create')->setPost()->check();
     *
     * // 方式2：传入自定义数据
     * $data = $validator->scene('create')->check(['username' => 'test', 'password' => '123456']);
     *
     * // 方式3：使用 setData 设置数据
     * $data = $validator->setData($customData)->scene('create')->check();
     * ```
     */
    public function check(?array $data = null): array
    {
        if ($this->_scene === null) {
            throw new ApiException(Code::VALIDATION_ERROR->value, '未指定场景，请先调用 scene() 或 sceneXxx()');
        }

        $data = $data ?? $this->_data ?? request()->all();

        return $this->validateData($data, $this->_scene, $this->_context);
    }

    // ═══════════════════════════════════════════════════════════════
    // 内部辅助方法
    // ═══════════════════════════════════════════════════════════════

    /**
     * 获取场景规则
     */
    protected function getSceneRules(?string $scene): array
    {
        $allRules = $this->rules();
        if ($scene === null) {
            return $allRules;
        }

        return $this->getRulesByScene($scene, $allRules);
    }

    /**
     * 解析上下文占位符
     */
    protected function resolveContextPlaceholders(array $rules, array $context): array
    {
        foreach ($rules as $field => &$fieldRules) {
            if (is_array($fieldRules)) {
                foreach ($fieldRules as $i => &$rule) {
                    if (is_string($rule) && isset($context[$rule])) {
                        $rule = $context[$rule];
                    }
                }
                unset($rule);
            }
        }
        unset($fieldRules);

        return $rules;
    }
}
