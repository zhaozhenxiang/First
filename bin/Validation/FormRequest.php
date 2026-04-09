<?php

declare(strict_types=1);

namespace Bin\Validation;

use Bin\Request\Request;

/**
 * 表单请求基类
 *
 * 将验证逻辑从 Controller 中解耦到独立的请求类。
 * 子类实现 rules() 定义验证规则，authorize() 定义授权逻辑。
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
     * 执行验证并处理结果
     *
     * @throws \RuntimeException 授权失败时 (403)
     */
    public function validateResolved(): void
    {
        if ($this->validationResolved) {
            return;
        }

        $this->validationResolved = true;

        if (!$this->authorize()) {
            throw new \RuntimeException('Unauthorized', 403);
        }

        $this->cachedRules = $this->rules();

        $this->validator = new ValidationManager($this->all(), $this->cachedRules);

        $messages = $this->messages();
        if ($messages !== []) {
            $this->validator->setCustomMessages($messages);
        }

        $this->validator->validate();

        if ($this->validator->hasErrors()) {
            $this->failedValidation($this->validator);
        }

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
     * 验证失败处理
     */
    protected function failedValidation(ValidationManager $validator): never
    {
        $errors = $validator->getErrors();
        $errorArray = $errors instanceof MessageBag ? $errors->all() : [];

        if ($this->expectsJson()) {
            header('Content-Type: application/json', true, 422);
            echo json_encode([
                'message' => 'The given data was invalid.',
                'errors' => $errorArray,
            ], JSON_THROW_ON_ERROR);
            exit;
        }

        // Web 请求：闪存旧输入 + 错误，重定向回上一页
        $this->flash();

        if (function_exists('session')) {
            $session = session();
            if ($session !== null && method_exists($session, 'flash')) {
                $session->flash('_errors', $errorArray);
            }
        }

        $referer = $this->header('REFERER') ?? '/';
        header("Location: {$referer}", true, 302);
        exit;
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
