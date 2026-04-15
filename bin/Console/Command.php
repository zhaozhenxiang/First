<?php

declare(strict_types=1);

namespace Bin\Console;

use Closure;
use RuntimeException;

/**
 * 命令基础抽象类
 */
abstract class Command
{
    /** @var string 命令名称 */
    protected string $name = '';

    /** @var string 命令描述 */
    protected string $description = '';

    /** @var array<string, string> 参数定义 */
    protected array $arguments = [];

    /** @var array<string, string> 选项定义 */
    protected array $options = [];

    /** @var array<string, mixed> 解析后的参数值 */
    private array $argumentValues = [];

    /** @var array<string, mixed> 解析后的选项值 */
    private array $optionValues = [];

    /** @var Input 输入接口 */
    protected Input $input;

    /** @var Output 输出接口 */
    protected Output $output;

    /**
     * 命令执行签名 (参数和选项定义)
     *
     * 示例: 'name {arg1} {arg2?} {--option} {--option=*}'
     */
    protected string $signature = '';

    /**
     * 执行命令
     */
    abstract public function execute(): int;

    /**
     * 获取命令名称
     */
    public function getName(): string
    {
        return $this->name ?: static::class;
    }

    /**
     * 设置命令名称
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 获取命令描述
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * 设置命令描述
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * 获取命令签名
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * 从签名解析命令名称和参数
     */
    public function parseSignature(): void
    {
        if (empty($this->signature)) {
            return;
        }

        $parts = explode(' ', trim($this->signature), 2);
        $this->name = $parts[0];

        if (isset($parts[1])) {
            $this->parseSignatureDefinition($parts[1]);
        }
    }

    /**
     * 解析签名定义
     *
     * 支持：
     *   参数: {name} {name?} {name=*} {name : description}
     *   选项: {--option} {--option=*} {--option=default} {--O|option} {--option : description}
     */
    private function parseSignatureDefinition(string $definition): void
    {
        // 匹配所有 {...} 块
        preg_match_all('/\{([^}]+)\}/', $definition, $matches);

        foreach ($matches[1] as $token) {
            $token = trim($token);

            if (str_starts_with($token, '--')) {
                $this->parseOptionToken(substr($token, 2));
            } else {
                $this->parseArgumentToken($token);
            }
        }
    }

    /**
     * 解析选项 token
     */
    private function parseOptionToken(string $token): void
    {
        // 提取描述（: 后面的部分）
        $description = '';
        if (str_contains($token, ' : ')) {
            [$token, $description] = explode(' : ', $token, 2);
        }

        // 短选项 {--O|option}
        $short = null;
        if (preg_match('/^(\w)\|(\w+)/', $token, $m)) {
            $short = $m[1];
            $name = $m[2];
            $token = substr($token, strlen("{$m[1]}|{$m[2]}"));
        } else {
            // 普通选项
            $parts = preg_split('/[=*]/', $token, 2);
            $name = $parts[0];
            $token = substr($token, strlen($name));
        }

        $isArray = str_contains($token, '*');
        $default = null;

        // 检查默认值 {--option=default}
        if (!$isArray && str_starts_with($token, '=')) {
            $default = substr($token, 1);
            if ($default === '') {
                $default = null;
            }
        }

        $this->options[$name] = [
            'type' => $isArray ? 'array' : ($default !== null ? 'string' : 'bool'),
            'required' => false,
            'default' => $default,
            'description' => trim($description),
        ];

        if ($short !== null) {
            $this->options[$short] = &$this->options[$name];
        }
    }

    /**
     * 解析参数 token
     */
    private function parseArgumentToken(string $token): void
    {
        // 提取描述
        $description = '';
        if (str_contains($token, ' : ')) {
            [$token, $description] = explode(' : ', $token, 2);
        }

        $isArray = str_contains($token, '=*');
        $isRequired = !str_contains($token, '?');
        $default = null;

        // {name?} → 可选，无默认值
        // {name=default} → 可选，有默认值
        if (!$isRequired) {
            $token = str_replace('?', '', $token);
        }

        if (str_contains($token, '=') && !$isArray) {
            [$name, $default] = explode('=', $token, 2);
        } else {
            $name = str_replace('=*', '', $token);
        }

        $this->arguments[$name] = [
            'required' => $isRequired && $default === null,
            'array' => $isArray,
            'default' => $default,
            'description' => trim($description),
        ];
    }

    /**
     * 获取参数定义
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * 获取选项定义
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * 运行命令
     */
    public function run(Input $input, Output $output): int
    {
        $this->input = $input;
        $this->output = $output;

        // 解析参数和选项
        $this->parseInput();

        return $this->execute();
    }

    /**
     * 解析输入
     */
    private function parseInput(): void
    {
        $args = $this->input->getArguments();
        $opts = $this->input->getOptions();

        // 解析参数
        $argKeys = array_keys($this->arguments);
        foreach ($argKeys as $index => $name) {
            $definition = $this->arguments[$name];
            $isArray = $definition['array'] ?? false;

            if ($isArray) {
                $this->argumentValues[$name] = array_slice($args, $index);
                break;
            }

            $this->argumentValues[$name] = $args[$index] ?? null;
        }

        // 解析选项
        foreach ($opts as $key => $value) {
            $this->optionValues[$key] = $value;
        }
    }

    /**
     * 获取参数值
     */
    public function argument(string $name, mixed $default = null): mixed
    {
        return $this->argumentValues[$name] ?? $default;
    }

    /**
     * 获取所有参数
     */
    public function arguments(): array
    {
        return $this->argumentValues;
    }

    /**
     * 获取选项值
     */
    public function option(string $name, mixed $default = null): mixed
    {
        return $this->optionValues[$name] ?? $default;
    }

    /**
     * 获取所有选项
     */
    public function options(): array
    {
        return $this->optionValues;
    }

    /**
     * 检查选项是否设置
     */
    public function hasOption(string $name): bool
    {
        return isset($this->optionValues[$name]);
    }

    /**
     * 确认操作
     */
    public function confirm(string $message, bool $default = false): bool
    {
        return $this->output->confirm($message, $default);
    }

    /**
     * 询问用户输入
     */
    public function ask(string $question, mixed $default = null): mixed
    {
        return $this->output->ask($question, $default);
    }

    /**
     * 选择选项
     */
    public function choice(string $question, array $options, mixed $default = null, bool $multiple = false): mixed
    {
        return $this->output->choice($question, $options, $default, $multiple);
    }

    /**
     * 秘密输入
     */
    public function secret(string $question): string
    {
        return $this->output->secret($question);
    }

    /**
     * 创建进度条
     */
    public function createProgressBar(int $max = 100): ProgressBar
    {
        return new ProgressBar($this->output, $max);
    }

    /**
     * 表格输出
     */
    public function table(array $headers, array $rows): void
    {
        $this->output->table($headers, $rows);
    }

    /**
     * 输出信息
     */
    public function info(string $message): void
    {
        $this->output->info($message);
    }

    /**
     * 输出成功消息
     */
    public function success(string $message): void
    {
        $this->output->success($message);
    }

    /**
     * 输出警告消息
     */
    public function warning(string $message): void
    {
        $this->output->warning($message);
    }

    /**
     * 输出错误消息
     */
    public function error(string $message): void
    {
        $this->output->error($message);
    }

    /**
     * 输出注释消息
     */
    public function comment(string $message): void
    {
        $this->output->comment($message);
    }

    /**
     * 输出行
     */
    public function line(string $message): void
    {
        $this->output->line($message);
    }

    /**
     * 输出空行
     */
    public function newLine(int $count = 1): void
    {
        $this->output->newLine($count);
    }

    /**
     * 输出标题
     */
    public function title(string $title): void
    {
        $this->output->title($title);
    }

    /**
     * 输出分节标题
     */
    public function section(string $title): void
    {
        $this->output->section($title);
    }

    /**
     * 获取输入实例
     */
    public function getInput(): Input
    {
        return $this->input;
    }

    /**
     * 获取输出实例
     */
    public function getOutput(): Output
    {
        return $this->output;
    }

    /**
     * 从容器中解析服务
     */
    public function laravel(string $abstract): mixed
    {
        return \Bin\App\App::getInstance()->make($abstract);
    }

    /**
     * 调用另一个命令
     */
    public function call(string $command, array $arguments = []): int
    {
        return Kernel::call($command, $arguments);
    }

    /**
     * 静默调用另一个命令（不输出）
     */
    public function callSilent(string $command, array $arguments = []): int
    {
        return Kernel::callSilent($command, $arguments);
    }
}
