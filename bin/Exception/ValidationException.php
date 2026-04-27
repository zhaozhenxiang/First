<?php

declare(strict_types=1);

namespace Bin\Exception;

/**
 * 验证失败异常
 */
class ValidationException extends \Exception
{
    /** @var array<string, string|array<string>> */
    private array $errors;

    /** @var string 错误袋名称 */
    private string $errorBag;

    /**
     * @param array<string, string|array<string>> $errors
     */
    public function __construct(array $errors, string $message = 'Validation failed', string $errorBag = 'default')
    {
        $this->errors = $errors;
        $this->errorBag = $errorBag;
        parent::__construct($message, 422);
    }

    /**
     * 获取所有验证错误
     * @return array<string, string|array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取第一个错误消息
     */
    public function getFirstError(): string
    {
        return array_values($this->errors)[0] ?? 'Validation failed';
    }

    /**
     * 获取错误袋名称
     */
    public function getErrorBag(): string
    {
        return $this->errorBag;
    }
}
