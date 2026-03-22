<?php

declare(strict_types=1);

namespace Bin\Validation;

use Bin\Exception\ValidationException;
use Bin\Session\SessionManager;
use Closure;

/**
 * 验证管理器 - 完整的表单验证系统
 */
class ValidationManager
{
    /** @var array<string, mixed> 待验证数据 */
    private array $data;

    /** @var array<string, string> 验证规则 */
    private array $rules;

    /** @var array<string, string> 字段别名 */
    private array $aliases = [];

    /** @var array<string, string> 自定义错误消息 */
    private array $customMessages = [];

    /** @var array<string, string> 错误消息 */
    private array $errors = [];

    /** @var array<string, mixed> 验证通过的数据 */
    private array $validated = [];

    /** @var bool 是否抛出异常 */
    private bool $throwOnFail = false;

    /** @var SessionManager|null Session 管理器 */
    private ?SessionManager $session = null;

    /**
     * 验证规则映射
     */
    private static array $ruleMethods = [
        'required' => 'validateRequired',
        'filled' => 'validateFilled',
        'nullable' => 'validateNullable',
        'string' => 'validateString',
        'integer' => 'validateInteger',
        'numeric' => 'validateNumeric',
        'boolean' => 'validateBoolean',
        'array' => 'validateArray',
        'email' => 'validateEmail',
        'url' => 'validateUrl',
        'ip' => 'validateIp',
        'json' => 'validateJson',
        'date' => 'validateDate',
        'alpha' => 'validateAlpha',
        'alpha_num' => 'validateAlphaNum',
        'alpha_dash' => 'validateAlphaDash',
        'min' => 'validateMin',
        'max' => 'validateMax',
        'between' => 'validateBetween',
        'size' => 'validateSize',
        'in' => 'validateIn',
        'not_in' => 'validateNotIn',
        'regex' => 'validateRegex',
        'confirmed' => 'validateConfirmed',
        'same' => 'validateSame',
        'different' => 'validateDifferent',
        'gt' => 'validateGt',
        'lt' => 'validateLt',
        'gte' => 'validateGte',
        'lte' => 'validateLte',
        'starts_with' => 'validateStartsWith',
        'ends_with' => 'validateEndsWith',
    ];

    /**
     * 自定义规则
     */
    private static array $customRules = [];

    /**
     * 默认错误消息
     */
    private static array $defaultMessages = [
        'required' => ':attribute 字段是必填的',
        'filled' => ':attribute 字段不能为空',
        'string' => ':attribute 必须是字符串',
        'integer' => ':attribute 必须是整数',
        'numeric' => ':attribute 必须是数字',
        'boolean' => ':attribute 必须是布尔值',
        'array' => ':attribute 必须是数组',
        'email' => ':attribute 必须是有效的邮箱地址',
        'url' => ':attribute 必须是有效的 URL',
        'ip' => ':attribute 必须是有效的 IP 地址',
        'json' => ':attribute 必须是有效的 JSON 字符串',
        'date' => ':attribute 必须是有效的日期',
        'alpha' => ':attribute 只能包含字母',
        'alpha_num' => ':attribute 只能包含字母和数字',
        'alpha_dash' => ':attribute 只能包含字母、数字和短横线',
        'min' => ':attribute 不能小于 :min',
        'max' => ':attribute 不能大于 :max',
        'between' => ':attribute 必须在 :min 到 :max 之间',
        'size' => ':attribute 的大小必须是 :size',
        'in' => ':attribute 的值必须在 :values 中',
        'not_in' => ':attribute 的值不能在 :values 中',
        'regex' => ':attribute 的格式无效',
        'confirmed' => ':attribute 两次输入不一致',
        'same' => ':attribute 和 :other 必须相同',
        'different' => ':attribute 和 :other 必须不同',
        'gt' => ':attribute 必须大于 :value',
        'lt' => ':attribute 必须小于 :value',
        'gte' => ':attribute 必须大于或等于 :value',
        'lte' => ':attribute 必须小于或等于 :value',
        'starts_with' => ':attribute 必须以 :values 开头',
        'ends_with' => ':attribute 必须以 :values 结尾',
    ];

    /**
     * 构造函数
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * 创建验证器实例
     */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    /**
     * 验证数据
     */
    public function validate(): array
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;
            $value = $this->getValue($field);
            $nullable = false;

            foreach ($rules as $rule) {
                $parameters = [];

                // 解析带参数的规则
                if (str_contains($rule, ':')) {
                    [$rule, $parameterString] = explode(':', $rule, 2);
                    $parameters = explode(',', $parameterString);
                }

                // 检查是否 nullable
                if ($rule === 'nullable') {
                    $nullable = true;
                    if ($value === null || $value === '') {
                        break;
                    }
                    continue;
                }

                // 跳过非必填字段（如果值为空）
                if (($value === null || $value === '') && $rule !== 'required' && !$this->isRequiredPresent($field)) {
                    continue;
                }

                // 执行验证
                if (!$this->validateRule($field, $value, $rule, $parameters)) {
                    break; // 停止该字段的后续验证
                }
            }

            // 验证通过，添加到已验证数据
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        if ($this->hasErrors()) {
            $this->handleFailure();
        }

        return $this->validated;
    }

    /**
     * 验证单个规则
     */
    private function validateRule(string $field, mixed $value, string $rule, array $parameters): bool
    {
        $method = self::$ruleMethods[$rule] ?? null;

        if ($method && method_exists($this, $method)) {
            $result = $this->$method($field, $value, $parameters);
            if (!$result) {
                $this->addError($field, $rule, $parameters);
                return false;
            }
        } elseif (isset(self::$customRules[$rule])) {
            $result = call_user_func(self::$customRules[$rule], $field, $value, $parameters, $this);
            if (!$result) {
                $this->addError($field, $rule, $parameters);
                return false;
            }
        }

        return true;
    }

    /**
     * 检查字段是否必填且存在
     */
    private function isRequiredPresent(string $field): bool
    {
        $rules = is_string($this->rules[$field]) ? explode('|', $this->rules[$field]) : $this->rules[$field];
        return in_array('required', $rules, true);
    }

    /**
     * 添加错误消息
     */
    private function addError(string $field, string $rule, array $parameters = []): void
    {
        $message = $this->customMessages["{$field}.{$rule}"]
            ?? $this->customMessages[$rule]
            ?? self::$defaultMessages[$rule]
            ?? ':attribute 验证失败';

        $attribute = $this->aliases[$field] ?? $field;

        // 替换占位符
        $message = str_replace(':attribute', $attribute, $message);

        if (!empty($parameters)) {
            $message = str_replace(':min', $parameters[0] ?? '', $message);
            $message = str_replace(':max', $parameters[1] ?? $parameters[0] ?? '', $message);
            $message = str_replace(':size', $parameters[0] ?? '', $message);
            $message = str_replace(':values', implode(', ', $parameters), $message);
            $message = str_replace(':value', $parameters[0] ?? '', $message);
            $message = str_replace(':other', $parameters[0] ?? '', $message);
        }

        $this->errors[$field] = $message;
    }

    /**
     * 获取字段值（支持点号分隔）
     */
    private function getValue(string $field): mixed
    {
        $segments = explode('.', $field);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !isset($value[$segment])) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * 处理验证失败
     */
    private function handleFailure(): void
    {
        if ($this->session) {
            $this->session->flash('errors', $this->errors);
            $this->session->flashInput($this->data);
        }

        if ($this->throwOnFail) {
            throw new ValidationException($this->errors);
        }
    }

    // ==================== 验证规则方法 ====================

    private function validateRequired(string $field, mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) > 0;
        }
        return $value !== null && $value !== '';
    }

    private function validateFilled(string $field, mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) > 0;
        }
        return $value !== null && $value !== '';
    }

    private function validateNullable(): bool
    {
        return true;
    }

    private function validateString(string $field, mixed $value): bool
    {
        return is_string($value);
    }

    private function validateInteger(string $field, mixed $value): bool
    {
        return is_numeric($value) && strpos((string) $value, '.') === false;
    }

    private function validateNumeric(string $field, mixed $value): bool
    {
        return is_numeric($value);
    }

    private function validateBoolean(string $field, mixed $value): bool
    {
        return is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1';
    }

    private function validateArray(string $field, mixed $value): bool
    {
        return is_array($value);
    }

    private function validateEmail(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateUrl(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateIp(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function validateJson(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function validateDate(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return strtotime($value) !== false;
    }

    private function validateAlpha(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[\pL\pM]+$/u', $value) > 0;
    }

    private function validateAlphaNum(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[\pL\pM0-9]+$/u', $value) > 0;
    }

    private function validateAlphaDash(string $field, mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[\pL\pM0-9_-]+$/u', $value) > 0;
    }

    private function validateMin(string $field, mixed $value, array $parameters): bool
    {
        $min = (float) ($parameters[0] ?? 0);

        if (is_numeric($value)) {
            return (float) $value >= $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    private function validateMax(string $field, mixed $value, array $parameters): bool
    {
        $max = (float) ($parameters[0] ?? 0);

        if (is_numeric($value)) {
            return (float) $value <= $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    private function validateBetween(string $field, mixed $value, array $parameters): bool
    {
        if (count($parameters) < 2) {
            return false;
        }

        $min = (float) $parameters[0];
        $max = (float) $parameters[1];

        if (is_numeric($value)) {
            return (float) $value >= $min && (float) $value <= $max;
        }

        if (is_string($value)) {
            $length = mb_strlen($value);
            return $length >= $min && $length <= $max;
        }

        if (is_array($value)) {
            $count = count($value);
            return $count >= $min && $count <= $max;
        }

        return false;
    }

    private function validateSize(string $field, mixed $value, array $parameters): bool
    {
        $size = (float) ($parameters[0] ?? 0);

        if (is_numeric($value)) {
            return (float) $value == $size;
        }

        if (is_string($value)) {
            return mb_strlen($value) == $size;
        }

        if (is_array($value)) {
            return count($value) == $size;
        }

        return false;
    }

    private function validateIn(string $field, mixed $value, array $parameters): bool
    {
        return in_array($value, $parameters, strict: true);
    }

    private function validateNotIn(string $field, mixed $value, array $parameters): bool
    {
        return !in_array($value, $parameters, strict: true);
    }

    private function validateRegex(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value) || !isset($parameters[0])) {
            return false;
        }
        return preg_match($parameters[0], $value) > 0;
    }

    private function validateConfirmed(string $field, mixed $value): bool
    {
        $confirmation = $this->getValue($field . '_confirmation');
        return $value === $confirmation;
    }

    private function validateSame(string $field, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0] ?? '');
        return $value === $other;
    }

    private function validateDifferent(string $field, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0] ?? '');
        return $value !== $other;
    }

    private function validateGt(string $field, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0] ?? '');

        if (is_numeric($value) && is_numeric($other)) {
            return (float) $value > (float) $other;
        }

        if (is_string($value) && is_string($other)) {
            return mb_strlen($value) > mb_strlen($other);
        }

        if (is_array($value) && is_array($other)) {
            return count($value) > count($other);
        }

        return false;
    }

    private function validateLt(string $field, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0] ?? '');

        if (is_numeric($value) && is_numeric($other)) {
            return (float) $value < (float) $other;
        }

        if (is_string($value) && is_string($other)) {
            return mb_strlen($value) < mb_strlen($other);
        }

        if (is_array($value) && is_array($other)) {
            return count($value) < count($other);
        }

        return false;
    }

    private function validateGte(string $field, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0] ?? '');

        if (is_numeric($value) && is_numeric($other)) {
            return (float) $value >= (float) $other;
        }

        if (is_string($value) && is_string($other)) {
            return mb_strlen($value) >= mb_strlen($other);
        }

        if (is_array($value) && is_array($other)) {
            return count($value) >= count($other);
        }

        return false;
    }

    private function validateLte(string $field, mixed $value, array $parameters): bool
    {
        $other = $this->getValue($parameters[0] ?? '');

        if (is_numeric($value) && is_numeric($other)) {
            return (float) $value <= (float) $other;
        }

        if (is_string($value) && is_string($other)) {
            return mb_strlen($value) <= mb_strlen($other);
        }

        if (is_array($value) && is_array($other)) {
            return count($value) <= count($other);
        }

        return false;
    }

    private function validateStartsWith(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        foreach ($parameters as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function validateEndsWith(string $field, mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        foreach ($parameters as $suffix) {
            if (str_ends_with($value, $suffix)) {
                return true;
            }
        }

        return false;
    }

    // ==================== 公共方法 ====================

    /**
     * 设置字段别名
     */
    public function setAliases(array $aliases): self
    {
        $this->aliases = $aliases;
        return $this;
    }

    /**
     * 设置自定义错误消息
     */
    public function setCustomMessages(array $messages): self
    {
        $this->customMessages = $messages;
        return $this;
    }

    /**
     * 验证失败时抛出异常
     */
    public function throwOnFail(bool $throw = true): self
    {
        $this->throwOnFail = $throw;
        return $this;
    }

    /**
     * 设置 Session 管理器
     */
    public function setSession(SessionManager $session): self
    {
        $this->session = $session;
        return $this;
    }

    /**
     * 检查是否有错误
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * 获取所有错误
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取第一个错误
     */
    public function getFirstError(): string
    {
        return $this->errors[array_key_first($this->errors)] ?? '';
    }

    /**
     * 获取指定字段的错误
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * 检查指定字段是否有错误
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * 获取已验证的数据
     */
    public function getValidated(): array
    {
        return $this->validated;
    }

    /**
     * 注册自定义验证规则
     */
    public static function extend(string $rule, Closure $callback): void
    {
        self::$customRules[$rule] = $callback;
    }

    /**
     * 设置默认错误消息
     */
    public static function setDefaultMessages(array $messages): void
    {
        self::$defaultMessages = array_merge(self::$defaultMessages, $messages);
    }

    // ==================== 静态辅助方法 ====================

    /**
     * 快速验证
     */
    public static function quickValidate(array $data, array $rules, array $messages = []): array
    {
        $validator = new self($data, $rules);
        $validator->setCustomMessages($messages);
        return $validator->validate();
    }

    /**
     * 验证并返回布尔结果
     */
    public static function check(array $data, array $rules): bool
    {
        $validator = new self($data, $rules);
        $validator->validate();
        return !$validator->hasErrors();
    }
}
