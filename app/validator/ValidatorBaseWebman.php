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
}
