<?php

declare(strict_types=1);

namespace Bin\Validation;

use Bin\Request\Request;
use Bin\Response\Response;

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
    /** @var ValidationManager|null 验证器实例 */
    protected ?ValidationManager $validator = null;

    /** @var array<string, mixed> 已验证的数据 */
    protected array $validatedData = [];

    /** @var bool 验证是否已执行 */
    protected bool $validationResolved = false;

    /**
     * 定义验证规则
     *
     * @return array<string, string|array>
     */
    abstract public function rules(): array;

    /**
     * 自定义错误消息
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * 自定义字段别名
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * 授权检查
     *
     * 返回 false 将拒绝访问（403）。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 执行验证并处理结果
     *
     * @throws \RuntimeException 授权失败时
     */
    public function validateResolved(): void
    {
        if ($this->validationResolved) {
            return;
        }

        $this->validationResolved = true;

        // 授权检查
        if (!$this->authorize()) {
            throw new \RuntimeException('Unauthorized', 403);
        }

        // 创建验证器
        $this->validator = new ValidationManager(
            $this->all(),
            $this->rules()
        );

        // 设置自定义消息
        $messages = $this->messages();
        if ($messages !== []) {
            $this->validator->setCustomMessages($messages);
        }

        // 执行验证
        $result = $this->validator->validate();
        $passed = $result === true || (is_array($result) && $result !== []);

        // 检查是否有错误
        $errors = $this->validator->getErrors();
        $hasErrors = ($errors instanceof MessageBag && !$errors->isEmpty())
            || (is_array($errors) && $errors !== []);

        if ($hasErrors) {
            $this->failedValidation($this->validator);
        }

        // 存储已验证数据
        $this->validatedData = $this->extractValidatedData();
    }

    /**
     * 获取已验证的数据
     *
     * 只返回在 rules() 中声明的字段数据。
     *
     * @throws \RuntimeException 验证未执行时
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
     *
     * 根据请求类型决定响应方式。
     */
    protected function failedValidation(ValidationManager $validator): never
    {
        // API 请求：返回 422 JSON
        if ($this->expectsJson()) {
            $errors = $validator->getErrors();
            $errorArray = $errors instanceof MessageBag
                ? $errors->all()
                : (is_array($errors) ? $errors : []);

            header('Content-Type: application/json', true, 422);
            echo json_encode([
                'message' => 'The given data was invalid.',
                'errors' => $errorArray,
            ], JSON_THROW_ON_ERROR);
            exit;
        }

        // Web 请求：闪存错误和旧输入，重定向回上一页
        $this->flash();

        $errorBag = $validator->getErrors();
        $errors = $errorBag instanceof MessageBag
            ? $errorBag->all()
            : (is_array($errorBag) ? $errorBag : []);

        if (function_exists('session')) {
            $session = session();
            if ($session !== null && method_exists($session, 'flash')) {
                $session->flash('_errors', $errors);
                $session->flash('_old_input', $this->all());
            }
        }

        // 重定向回上一页
        $referer = $this->header('REFERER') ?? '/';
        header("Location: {$referer}", true, 302);
        exit;
    }

    /**
     * 获取验证器实例
     */
    public function getValidator(): ?ValidationManager
    {
        return $this->validator;
    }

    /**
     * 提取已验证的数据（只取 rules 中声明的字段）
     */
    protected function extractValidatedData(): array
    {
        $rules = $this->rules();
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
