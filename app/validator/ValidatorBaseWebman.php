<?php
declare(strict_types=1);

namespace plugin\nanoadmin\app\validator;

use Closure;
use InvalidArgumentException;
use support\validation\Rule as IlluminateRule;
use support\validation\ValidationException;
use plugin\nanoadmin\app\common\ApiException;
use plugin\nanoadmin\app\common\Code;

/**
 * 基于 webman/validation（illuminate/validation）的表单验证器基类
 *
 * 对齐 think-validate 风格的使用方式：
 * - 支持 scenes() 定义验证场景
 * - 支持 validateData() 核心校验方法
 * - 支持闭包注入 excludeId 用于 update unique 校验
 *
 * 注意：不继承 webman/validation Validator，而是直接使用其 ValidationFactory。
 * 原因：webman/validation Validator::make() 是 static 方法，内部通过 Container::make()
 * 创建新实例时会丢失子类的 scenes 场景定义，导致 withScene() 失效。
 *
 * @method string get(string $name, mixed $default = null) 获取GET参数
 * @method string post(string $name, mixed $default = null) 获取POST参数
 * @method mixed input(string $name, mixed $default = null) 获取输入参数
 * @method array all() 获取所有参数
 */
abstract class ValidatorBaseWebman
{
    use ValidatorRequestTrait;

    /**
     * 获取验证规则
     *
     * @return array<string, array<string>|Closure>
     */
    abstract public function rules(): array;

    /**
     * 获取场景定义
     *
     * @return array<string, array<string>|Closure>
     */
    public function scenes(): array
    {
        return [];
    }

    /**
     * 获取自定义消息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * 获取自定义属性名
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * 获取场景对应的验证规则
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

        $sceneDefs = $this->scenes();
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
     * 直接使用 illuminate/validation factory 创建 validator（绕开 webman/validation 的 static make 陷阱）
     *
     * @param array $data
     * @param array $rules
     * @param array|null $messages
     * @param array|null $attributes
     * @return \Illuminate\Validation\Validator
     */
    protected function buildValidator(
        array $data,
        array $rules,
        ?array $messages = null,
        ?array $attributes = null
    ): \Illuminate\Validation\Validator {
        $factory = \Webman\Validation\Factory\ValidationFactory::getFactory();
        return $factory->make($data, $rules, $messages ?? $this->messages(), $attributes ?? $this->attributes());
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
     * 验证数据
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

        $validator = $this->buildValidator($data, $rules);

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
     * 获取当前请求的所有参数（包含路由参数）
     *
     * @return array
     */
    public function all(): array
    {
        return request()->all() + request()->route->param();
    }

    /**
     * 检查字段是否存在
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        $data = request()->all() + request()->route->param();
        return isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null;
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
