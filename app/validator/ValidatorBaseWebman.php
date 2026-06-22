<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator;

use Closure;
use Webman\Validation\Validator as BaseValidator;
use support\validation\Rule as IlluminateRule;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;

/**
 * 基于 webman/validation（support\validation\Validator）的表单验证器基类
 *
 * 对齐 think-validate 风格的使用方式：
 * - 支持 scenes() 定义验证场景（支持闭包）
 * - 支持 validateData() 核心校验方法
 * - 支持闭包注入 excludeId 用于 update unique 校验
 *
 * 继承 support\validation\Validator，提供：
 * - make() 静态工厂方法
 * - withScene() 场景方法
 * - validate() / fails() / errors() 验证方法
 *
 * @method static self make(array $data, ?array $rules = null, ?array $messages = null, ?array $attributes = null)
 * @method self withScene(string $scene)
 * @method void validate()
 * @method bool fails()
 * @method \support\validation\MessageBag errors()
 * @method array validated()
 *
 * @author NanoAdmin Team
 * @since 1.0.0
 */
abstract class ValidatorBaseWebman extends BaseValidator
{
    use ValidatorRequestTrait;

    /**
     * 验证规则（由子类通过 rules() 方法提供）
     *
     * @var array<string, array<string>|Closure|string>
     */
    protected array $rules = [];

    /**
     * 场景定义
     *
     * @var array<string, array<string>|Closure>
     */
    protected array $scenes = [];

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

    // ── goCheck 兼容层状态属性 ──────────────────────────────
    /** @var array 当前待校验数据 */
    protected array $_data = [];
    /** @var string|null 当前绑定场景名 */
    protected ?string $_scene = null;
    /** @var array 上下文（excludeId 等） */
    protected array $_context = [];
    /** @var array only() 收集的字段 */
    protected array $_sceneFields = [];
    /** @var array append() 收集的 field => [methods] */
    protected array $_sceneAppends = [];
    /** @var array remove() 收集的字段 */
    protected array $_sceneRemoves = [];

    /**
     * 获取验证规则
     *
     * 兼容两种写法：
     * 1. 属性定义：protected array $rules = ['field' => 'required'];
     * 2. 方法定义：public function rules(): array { return ['field' => 'required']; }
     *
     * 如果设置了场景，则返回场景对应的规则（支持闭包）
     *
     * @return array<string, array<string>|Closure|string>
     */
    public function rules(): array
    {
        // 获取当前场景
        $scene = $this->scene();
        
        // 获取全部规则
        $allRules = $this->getAllRules();
        
        if ($scene === null) {
            return $allRules;
        }

        // 获取场景定义的规则
        return $this->getRulesByScene($scene, $allRules);
    }

    /**
     * 获取全部验证规则
     *
     * @return array<string, array<string>|Closure|string>
     */
    private function getAllRules(): array
    {
        $class = static::class;
        
        try {
            // 检测子类是否覆盖了 rules() 方法
            $reflection = new \ReflectionMethod($class, 'rules');
            if ($reflection->getDeclaringClass()->getName() === $class) {
                // 创建临时实例调用子类的方法
                $tempValidator = new $class();
                return $reflection->invoke($tempValidator);
            }
        } catch (\Throwable) {
            // 忽略
        }
        
        // 回退到属性
        return $this->rules;
    }

    /**
     * 根据场景获取规则
     *
     * @param string $scene
     * @param array $allRules
     * @return array
     */
    private function getRulesByScene(string $scene, array $allRules): array
    {
        // 通过反射调用 scenes() 方法
        $class = static::class;
        try {
            $reflection = new \ReflectionMethod($class, 'scenes');
            if ($reflection->getDeclaringClass()->getName() === $class) {
                $tempValidator = new $class();
                $sceneDefs = $reflection->invoke($tempValidator);
            } else {
                $sceneDefs = $this->scenes;
            }
        } catch (\Throwable) {
            $sceneDefs = $this->scenes;
        }

        if (!isset($sceneDefs[$scene])) {
            return $allRules;
        }

        $sceneFields = $sceneDefs[$scene];

        // 支持闭包场景
        if ($sceneFields instanceof \Closure) {
            return $sceneFields($allRules, $this->data());
        }

        // 返回场景定义的字段对应的规则
        return array_intersect_key($allRules, array_flip($sceneFields));
    }

    /**
     * 获取场景定义
     *
     * 兼容两种写法：
     * 1. 属性定义：protected array $scenes = ['create' => ['field']];
     * 2. 方法定义：public function scenes(): array { return ['create' => ['field']]; }
     *
     * @return array<string, array<string>|Closure>
     */
    public function scenes(): array
    {
        // 子类覆盖时会返回自己的场景
        return $this->scenes;
    }

    /**
     * 获取自定义消息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * 获取自定义属性名
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * 创建验证器实例
     *
     * 注意：不覆盖 $this->rules 属性，让子类通过 rules() 方法提供规则
     *
     * @param array $data
     * @param array|null $rules 如果为 null，则使用子类 rules() 方法
     * @param array|null $messages
     * @param array|null $attributes
     * @return static
     */
    public static function make(
        array $data,
        ?array $rules = null,
        ?array $messages = null,
        ?array $attributes = null
    ): static {
        $instance = new static();
        $instance->data = $data;
        
        // 只设置 messages 和 attributes，不设置 rules
        // 让 rules() 方法始终返回子类定义的规则
        if ($messages !== null) {
            $instance->messages = $messages;
        }
        if ($attributes !== null) {
            $instance->attributes = $attributes;
        }

        return $instance;
    }

    /**
     * 创建 Illuminate 验证器
     *
     * 覆盖父类方法，使用子类 rules() 方法获取规则
     */
    public function toIlluminate(): \Illuminate\Validation\Validator
    {
        $factory = \Webman\Validation\Factory\ValidationFactory::getFactory();
        
        // 清除父类的缓存
        $reflection = new \ReflectionClass(\Webman\Validation\Validator::class);
        $prop = $reflection->getProperty('validator');
        $prop->setAccessible(true);
        $prop->setValue($this, null);
        
        // 创建验证器
        $this->illuminateValidator = $factory->make(
            $this->data(),
            $this->rules(),
            $this->messages(),
            $this->attributes()
        );
        
        // 缓存到父类属性
        $prop->setValue($this, $this->illuminateValidator);
        
        return $this->illuminateValidator;
    }
    
    /**
     * 缓存的 Illuminate 验证器实例
     */
    private ?\Illuminate\Validation\Validator $illuminateValidator = null;

    /**
     * 获取场景对应的验证规则（支持闭包）
     *
     * @param string|null $scene
     * @param array $context 上下文数据
     * @return array
     */
    protected function getSceneRules(?string $scene, array $context = []): array
    {
        $allRules = $this->rules();
        if ($scene === null) {
            return $allRules;
        }

        $sceneDefs = array_merge($this->scenes, $this->scenes());
        if (!isset($sceneDefs[$scene])) {
            return $allRules;
        }

        $sceneFields = $sceneDefs[$scene];
        if ($sceneFields instanceof \Closure) {
            return $sceneFields($allRules, $context);
        }

        return array_intersect_key($allRules, array_flip($sceneFields));
    }

    /**
     * 解析规则中的上下文占位符
     *
     * @param array $rules
     * @param array $context
     * @return array
     */
    protected function resolveRulesWithContext(array $rules, array $context): array
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

    /**
     * 是否启用快速失败模式
     */
    protected bool $stopOnFirstFailure = true;

    /**
     * 验证数据（核心方法，支持闭包场景）
     *
     * @param array $data 要验证的数据
     * @param string|null $scene 验证场景
     * @param array $context 额外的上下文数据（如 excludeId）
     * @return array 验证通过的数据
     * @throws ApiException
     */
    public function validateData(array $data, ?string $scene = null, array $context = []): array
    {
        $rules = $this->getSceneRules($scene, $context);
        $rules = $this->resolveRulesWithContext($rules, $context);

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
     * 验证更新数据（排除当前记录的唯一性检查）
     *
     * @param array $data 要验证的数据
     * @param int $excludeId 要排除的记录ID
     * @param string|null $scene 验证场景，默认 'update'
     * @return array
     * @throws ApiException
     */
    public function validateUpdateData(array $data, int $excludeId, ?string $scene = 'update'): array
    {
        return $this->validateData($data, $scene, ['excludeId' => $excludeId]);
    }

    /**
     * 获取经过验证的请求数据
     *
     * @param string|null $scene 验证场景
     * @param array $context 额外的上下文数据
     * @return array
     * @throws ApiException
     */
    public function validateRequest(?string $scene = null, array $context = []): array
    {
        return $this->validateData($this->all(), $scene, $context);
    }

    /**
     * 验证登录参数
     *
     * @param array $data
     * @return array
     * @throws ApiException
     */
    public function validateLoginData(array $data): array
    {
        return $this->validateData($data, 'login');
    }

    /**
     * 验证ID参数
     *
     * @param int|string $id
     * @return int
     * @throws ApiException
     */
    public function validateId($id): int
    {
        $data = $this->validateData(['id' => $id], 'show');
        return (int) $data['id'];
    }

    /**
     * 静态工厂方法：创建验证器实例并验证数据
     *
     * @param array $data
     * @param string|null $scene
     * @param array $context
     * @return array
     * @throws ApiException
     */
    public static function check(array $data, ?string $scene = null, array $context = []): array
    {
        $validator = new static();
        return $validator->validateData($data, $scene, $context);
    }

    /**
     * 生成 unique 规则（用于 illuminate/validation）
     *
     * @param string $table 表名
     * @param string $column 字段名
     * @param int|null $excludeId 排除的ID
     * @param string $idColumn 主键字段名
     * @return \Illuminate\Validation\Rules\Unique
     */
    protected static function unique(
        string $table,
        string $column,
        ?int $excludeId = null,
        string $idColumn = 'id'
    ): \Illuminate\Validation\Rules\Unique {
        $rule = IlluminateRule::unique($table, $column);
        if ($excludeId !== null) {
            $rule = $rule->ignore($excludeId, $idColumn);
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

    /**
     * 条件规则
     *
     * @param Closure|bool $condition
     * @param array $rules
     * @param array|null $defaultRules
     * @return array
     */
    protected static function when(
        Closure|bool $condition,
        array $rules,
        ?array $defaultRules = null
    ): array {
        if ($condition instanceof Closure) {
            $condition = $condition(request()->all());
        }
        return $condition ? $rules : ($defaultRules ?? []);
    }

    // ═══════════════════════════════════════════════════════
    // goCheck 兼容层：likeadmin 风格链式 API
    // ═══════════════════════════════════════════════════════

    /**
     * 注入上下文（如 excludeId），在 sceneXxx() 之前调用
     */
    public function withContext(array $context): static
    {
        $this->_context = array_merge($this->_context, $context);
        return $this;
    }

    /**
     * 捕获 POST 数据（替代有冲突的 post() 方法）
     */
    public function setPost(): static
    {
        $this->_data = request()->post();
        return $this;
    }

    /**
     * 捕获 GET 数据（替代有冲突的 get() 方法）
     */
    public function setGet(): static
    {
        $this->_data = request()->get();
        return $this;
    }

    /**
     * 自定义数据源（用于测试或手动传参）
     * 注意：父类已有 data(): array 方法，此处用 setData 避免冲突
     */
    public function setData(array $data): static
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * Magic __call：将 ->sceneXxx() 反射到 sceneXxx() 方法并返回 $this
     * 配合 goCheck() 无参数使用（推荐风格 B）
     */
    public function __call(string $name, array $args): static
    {
        if (str_starts_with($name, 'scene')) {
            $scene = lcfirst(substr($name, 5));
            return $this->_bindScene($scene);
        }
        throw new \BadMethodCallException("方法 {$name} 不存在");
    }

    /**
     * 内部：绑定场景，调用 sceneXxx() 方法
     */
    protected function _bindScene(string $scene): static
    {
        $this->_scene = $scene;
        // 同步到 webman validator 内部属性，使 rules() 中的 scene() 调用能读到正确值
        $reflection = new \ReflectionClass(\Webman\Validation\Validator::class);
        $prop = $reflection->getProperty('scene');
        $prop->setAccessible(true);
        $prop->setValue($this, $scene);
        return $this;
    }

    /**
     * 场景白名单：等价于 think-validate 的 only()
     */
    public function only(array $fields): static
    {
        $this->_sceneFields = array_unique(array_merge($this->_sceneFields ?? [], $fields));
        return $this;
    }

    /**
     * 追加字段 + 自定义校验方法：等价于 think-validate 的 append()
     * 例：->append('business_type_other', 'checkBusinessTypeOther')
     */
    public function append(string $field, string $method): static
    {
        $this->_sceneAppends[$field][] = $method;
        return $this;
    }

    /**
     * 场景移除字段：等价于 think-validate 的 remove()
     */
    public function remove(string $field): static
    {
        $this->_sceneRemoves[] = $field;
        return $this;
    }

    /**
     * 字段中文名映射：等价于 think-validate 的 $field
     */
    public function field(string $name, string $label): static
    {
        $this->attributes[$name] = $label;
        return $this;
    }

    /**
     * 执行校验（无参数，推荐风格 B）
     * 场景已在前面通过 sceneXxx() / _bindScene() 绑定
     *
     * @return array 验证通过的数据
     * @throws ApiException
     */
    public function goCheck(): array
    {
        if ($this->_scene === null) {
            throw new ApiException(Code::VALIDATION_ERROR->value, '未指定场景，请先调用 sceneXxx()');
        }

        $data = $this->_data ?? request()->all();

        // 如果有 only/append/remove，修改规则
        if (!empty($this->_sceneFields) || !empty($this->_sceneAppends) || !empty($this->_sceneRemoves)) {
            return $this->_goCheckWithChain($data);
        }

        return $this->validateData($data, $this->_scene, $this->_context);
    }

    /**
     * 内部：使用链式 only/append/remove 执行校验
     */
    protected function _goCheckWithChain(array $data): array
    {
        // 优先使用 sceneXxx() 方法返回的规则；若没有则走 scenes() 数组
        $allRules = $this->rules();
        $sceneRules = $this->getSceneRules($this->_scene, $this->_context);

        // 应用 only 白名单
        if (!empty($this->_sceneFields)) {
            $sceneRules = array_intersect_key($sceneRules, array_flip($this->_sceneFields));
        }

        // 应用 remove 移除字段
        if (!empty($this->_sceneRemoves)) {
            foreach ($this->_sceneRemoves as $field) {
                unset($sceneRules[$field]);
            }
        }

        // 应用 append 追加自定义方法
        if (!empty($this->_sceneAppends)) {
            foreach ($this->_sceneAppends as $field => $methods) {
                foreach ($methods as $method) {
                    if (method_exists($this, $method)) {
                        $sceneRules[$field][] = function ($attr, $value, $fail) use ($method, $data) {
                            $result = $this->{$method}($value, null, $data);
                            if ($result !== true) {
                                $fail(is_string($result) ? $result : '校验失败');
                            }
                        };
                    }
                }
            }
        }

        // resolveContext 占位符
        $sceneRules = $this->resolveRulesWithContext($sceneRules, $this->_context);

        $factory = \Webman\Validation\Factory\ValidationFactory::getFactory();
        $validator = $factory->make($data, $sceneRules, $this->messages(), $this->attributes());

        if ($this->stopOnFirstFailure) {
            $validator->stopOnFirstFailure();
        }

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            throw new ApiException(Code::VALIDATION_ERROR->value, implode('; ', $errors));
        }

        // 重置状态
        $this->_resetChainState();

        return $validator->validated();
    }

    /**
     * 重置链式状态
     */
    protected function _resetChainState(): void
    {
        $this->_data = [];
        $this->_scene = null;
        $this->_context = [];
        $this->_sceneFields = [];
        $this->_sceneAppends = [];
        $this->_sceneRemoves = [];
        // 同步重置 webman validator 内部 scene 属性
        $reflection = new \ReflectionClass(\Webman\Validation\Validator::class);
        $prop = $reflection->getProperty('scene');
        $prop->setAccessible(true);
        $prop->setValue($this, null);
    }
}
