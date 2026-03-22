<?php

declare(strict_types=1);

namespace Bin\Console;

use RuntimeException;

/**
 * 输入处理
 */
class Input
{
    /** @var array<string, mixed> 命令行参数 */
    private array $argv;

    /** @var array<string, mixed> 解析后的参数 */
    private array $arguments = [];

    /** @var array<string, mixed> 解析后的选项 */
    private array $options = [];

    /** @var string 命令名称 */
    private string $commandName;

    /** @var resource|null STDIN 资源 */
    private $stdin;

    /**
     * 构造函数
     */
    public function __construct(array $argv = null)
    {
        $this->argv = $argv ?? $_SERVER['argv'];
        $this->stdin = fopen('php://stdin', 'r');

        $this->parse();
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        if (is_resource($this->stdin)) {
            fclose($this->stdin);
        }
    }

    /**
     * 解析命令行输入
     */
    private function parse(): void
    {
        if (count($this->argv) < 2) {
            return;
        }

        // 跳过脚本名称
        array_shift($this->argv);

        // 第一个是命令名称
        $this->commandName = array_shift($this->argv);

        // 解析参数和选项
        $arguments = [];
        $iterate = $this->argv;

        for ($i = 0; $i < count($iterate); $i++) {
            $arg = $iterate[$i];

            if (str_starts_with($arg, '--')) {
                // 长选项 --option 或 --option=value
                $parts = explode('=', substr($arg, 2), 2);
                $option = $parts[0];
                $value = $parts[1] ?? true;

                if ($value === true && isset($iterate[$i + 1]) && !str_starts_with($iterate[$i + 1], '-')) {
                    $value = $iterate[$i + 1];
                    $i++;
                }

                $this->options[$option] = $value;
            } elseif (str_starts_with($arg, '-')) {
                // 短选项 -a 或 -abc
                $flags = substr($arg, 1);

                for ($j = 0; $j < strlen($flags); $j++) {
                    $flag = $flags[$j];
                    $this->options[$flag] = true;
                }
            } else {
                // 参数
                $arguments[] = $arg;
            }
        }

        $this->arguments = $arguments;
    }

    /**
     * 获取命令名称
     */
    public function getCommandName(): string
    {
        return $this->commandName;
    }

    /**
     * 获取第一个参数
     */
    public function getFirstArgument(): string
    {
        return $this->arguments[0] ?? '';
    }

    /**
     * 获取所有参数
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * 获取指定位置的参数
     */
    public function getArgument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * 检查参数是否存在
     */
    public function hasArgument(int $index): bool
    {
        return isset($this->arguments[$index]);
    }

    /**
     * 获取所有选项
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * 获取指定选项
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * 检查选项是否存在
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * 读取一行输入
     */
    public function readLine(): string
    {
        return trim(fgets($this->stdin) ?: '');
    }

    /**
     * 读取密码（不显示输入）
     */
    public function readSecret(): string
    {
        system('stty -echo');
        $input = $this->readLine();
        system('stty echo');
        echo PHP_EOL;

        return $input;
    }

    /**
     * 确认操作
     */
    public function confirm(string $message, bool $default = false): bool
    {
        $defaultChar = $default ? 'Y' : 'N';
        $message = "{$message} (yes/no) [{$defaultChar}]: ";

        $input = strtolower($this->prompt($message));

        if ($input === '') {
            return $default;
        }

        return in_array($input, ['y', 'yes', '1', 'true'], true);
    }

    /**
     * 提示用户输入
     */
    public function prompt(string $message, mixed $default = null): string
    {
        if ($default !== null) {
            $message .= " [{$default}]: ";
        } else {
            $message .= ': ';
        }

        echo $message;
        $input = $this->readLine();

        if ($input === '' && $default !== null) {
            return (string) $default;
        }

        return $input;
    }

    /**
     * 选择选项
     */
    public function choice(string $message, array $options, mixed $default = null): string
    {
        echo $message . PHP_EOL;

        foreach ($options as $key => $label) {
            $displayKey = is_string($key) ? $key : $key;
            echo "  [<comment>{$displayKey}</comment>] {$label}" . PHP_EOL;
        }

        $selected = $this->prompt('>', $default);

        if (!isset($options[$selected])) {
            throw new RuntimeException("Invalid option: {$selected}");
        }

        return $selected;
    }

    /**
     * 多选
     */
    public function multichoice(string $message, array $options, array $default = []): array
    {
        echo $message . PHP_EOL;

        foreach ($options as $key => $label) {
            $displayKey = is_string($key) ? $key : $key;
            $selected = in_array($key, $default, true) ? '<info>✓</info>' : ' ';
            echo "  [{$selected}] [<comment>{$displayKey}</comment>] {$label}" . PHP_EOL;
        }

        $input = $this->prompt('>', implode(',', $default));
        $selected = array_map('trim', explode(',', $input));

        foreach ($selected as $value) {
            if (!isset($options[$value])) {
                throw new RuntimeException("Invalid option: {$value}");
            }
        }

        return $selected;
    }

    /**
     * 交互式表单
     */
    public function form(array $fields): array
    {
        $results = [];

        foreach ($fields as $name => $config) {
            $label = $config['label'] ?? $name;
            $default = $config['default'] ?? null;
            $secret = $config['secret'] ?? false;
            $validate = $config['validate'] ?? null;

            do {
                if ($secret) {
                    $value = $this->secret($label);
                } else {
                    $value = $this->prompt($label, $default);
                }

                if ($validate !== null) {
                    $error = null;
                    if (is_string($validate)) {
                        // 正则验证
                        if (!preg_match($validate, $value)) {
                            $error = $config['error'] ?? 'Invalid input';
                        }
                    } elseif (is_callable($validate)) {
                        // 自定义验证
                        $result = call_user_func($validate, $value);
                        if ($result !== true) {
                            $error = is_string($result) ? $result : 'Invalid input';
                        }
                    }

                    if ($error !== null) {
                        $this->error("<error>{$error}</error>");
                        continue;
                    }
                }

                $results[$name] = $value;
                break;
            } while (true);
        }

        return $results;
    }
}
