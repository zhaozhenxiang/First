<?php

declare(strict_types=1);

namespace Bin\Validation;

use Bin\Exception\AuthorizationException;
use Bin\Exception\ValidationException;
use Bin\Request\Request;
use Closure;

/**
 * 表单请求基类
 *
 * 将验证逻辑从 Controller 中解耦到独立的请求类。
 * 子类实现 rules() 定义验证规则，authorize() 定义授权逻辑。
 *
 * 生命周期：authorize → prepareForValidation → rules → validate → after → passedValidation
 *
 * 验证失败自动响应：
 * - Web 请求：重定向回上一页 + 闪存错误和旧输入
 * - API 请求：返回 422 JSON
 */
abstract class FormRequest extends Request
{
    protected ?ValidationManager $validator = null;
    protected array $validatedData = [];
    protected bool $validationResolved = false;

    /** @var array<string, mixed>|null 缓存的 rules，避免重复调用 */
    protected ?array $cachedRules = null;

    /** @var string 错误袋名称 */
    protected string $errorBag = 'default';

    /** @var array<Closure> 验证后回调 */
    protected array $afterCallbacks = [];

    abstract public function rules(): array;

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 验证前预处理钩子
     *
     * 子类可覆盖此方法在验证运行前修改输入数据。
     * 此时 authorize() 已通过，但 rules() 尚未执行。
     */
    protected function prepareForValidation(): void
    {
    }

    /**
     * 验证通过后钩子
     *
     * 子类可覆盖此方法在验证通过后执行额外操作（如日志、事件派发等）。
     * 此时 validatedData 尚未提取。
     */
    protected function passedValidation(): void
    {
    }

    /**
     * 注册验证后回调
     *
     * 回调接收 ValidationManager 实例，可在验证规则通过后追加额外错误：
     *   $request->after(function (ValidationManager $validator) {
     *       if (someCondition()) {
     *           $validator->getErrors()->add('field', 'Custom error');
     *       }
     *   });
     */
    public function after(Closure $callback): static
    {
        $this->afterCallbacks[] = $callback;
        return $this;
    }

    /**
     * 获取错误袋名称
     */
    public function errorBag(): string
    {
        return $this->errorBag;
    }

    /**
     * 设置错误袋名称
     */
    public function setErrorBag(string $bag): static
    {
        $this->errorBag = $bag;
        return $this;
    }

    /**
     * 执行验证并处理结果
     *
     * 生命周期：
     * 1. authorize() — 授权检查，失败走 failedAuthorization()
     * 2. prepareForValidation() — 预处理输入
     * 3. rules() → 创建 ValidationManager
     * 4. validate() — 执行验证规则
     * 5. after callbacks — 后置回调（可追加错误）
     * 6. 检查错误 → failedValidation()
     * 7. passedValidation() — 验证通过钩子
     * 8. 提取 validatedData
     */
    public function validateResolved(): void
    {
        if ($this->validationResolved) {
            return;
        }
        $this->validationResolved = true;

        // 1. 授权检查
        if (!$this->authorize()) {
            $this->failedAuthorization();
        }

        // 2. 验证前预处理
        $this->prepareForValidation();

        // 3. 获取规则并创建验证器
        $this->cachedRules = $this->rules();
        $this->validator = new ValidationManager($this->all(), $this->cachedRules);

        $messages = $this->messages();
        if ($messages !== []) {
            $this->validator->setCustomMessages($messages);
        }

        $attributes = $this->attributes();
        if ($attributes !== []) {
            $this->validator->setAliases($attributes);
        }

        // 4. 执行验证
        $this->validator->validate();

        // 5. 规则验证通过后执行 after 回调（可追加额外错误）
        if (!$this->validator->hasErrors()) {
            foreach ($this->afterCallbacks as $callback) {
                $callback($this->validator);
            }
        }

        // 6. 检查错误（含 after 回调添加的）
        if ($this->validator->hasErrors()) {
            $this->failedValidation($this->validator);
        }

        // 7. 验证通过钩子
        $this->passedValidation();

        // 8. 提取已验证数据
        $this->validatedData = $this->extractValidatedData();
    }

    /**
     * 获取已验证的数据（只返回 rules 中声明的字段）
     */
    public function validated(): array
    {
        if (!$this->validationResolved) {
            $this->validateResolved();
        }

        return $this->validatedData;
    }

    /**
     * 授权失败处理
     *
     * 抛出 AuthorizationException，由 ExceptionHandler 统一渲染。
     */
    protected function failedAuthorization(): never
    {
        throw new AuthorizationException();
    }

    /**
     * 验证失败处理
     *
     * 抛出 ValidationException，由 ExceptionHandler 统一渲染。
     */
    protected function failedValidation(ValidationManager $validator): never
    {
        $errors = $validator->getErrors();
        $errorArray = $errors instanceof MessageBag ? $errors->all() : [];

        throw new ValidationException(
            $errorArray,
            'The given data was invalid.'
        );
    }

    public function getValidator(): ?ValidationManager
    {
        return $this->validator;
    }

    protected function extractValidatedData(): array
    {
        $rules = $this->cachedRules ?? $this->rules();
        $allData = $this->all();
        $validated = [];

        foreach (array_keys($rules) as $field) {
            if (data_has($allData, $field)) {
                $validated[$field] = data_get($allData, $field);
            }
        }

        return $validated;
    }
}
