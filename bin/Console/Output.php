<?php

declare(strict_types=1);

namespace Bin\Console;

use Bin\Validation\Validator;

/**
 * 输出处理
 */
class Output
{
    /** @var resource|null STDOUT 资源 */
    private $stdout;

    /** @var resource|null STDERR 资源 */
    private $stderr;

    /** @var bool 是否支持 ANSI 颜色 */
    private bool $supportsColor;

    /** @var array<string, string> ANSI 颜色代码 */
    private array $colors = [
        'black' => '30',
        'red' => '31',
        'green' => '32',
        'yellow' => '33',
        'blue' => '34',
        'magenta' => '35',
        'cyan' => '36',
        'white' => '37',
        'default' => '39',
    ];

    /** @var array<string, string> ANSI 背景颜色代码 */
    private array $bgColors = [
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'white' => '47',
        'default' => '49',
    ];

    /** @var array<string, string> ANSI 样式代码 */
    private array $styles = [
        'bold' => '1',
        'dim' => '2',
        'italic' => '3',
        'underline' => '4',
        'blink' => '5',
        'reverse' => '7',
        'hidden' => '8',
        'strikethrough' => '9',
    ];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->stdout = fopen('php://stdout', 'w');
        $this->stderr = fopen('php://stderr', 'w');
        $this->supportsColor = $this->detectColorSupport();
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }
        if (is_resource($this->stderr)) {
            fclose($this->stderr);
        }
    }

    /**
     * 检测颜色支持
     */
    private function detectColorSupport(): bool
    {
        // 检查 --no-color 选项
        global $argv;
        if (in_array('--no-color', $argv ?? [], true)) {
            return false;
        }

        // 检查 COLORTERM 环境变量
        if (isset($_SERVER['COLORTERM']) && $_SERVER['COLORTERM'] === 'truecolor') {
            return true;
        }

        // 检查是否在支持颜色的终端中
        if (PHP_SAPI === 'cli') {
            // 检查 NO_COLOR 环境变量
            if (isset($_SERVER['NO_COLOR'])) {
                return false;
            }

            // 检查 FORCE_COLOR 环境变量
            if (isset($_SERVER['FORCE_COLOR']) && $_SERVER['FORCE_COLOR'] !== '0') {
                return true;
            }

            // Windows 10+ 支持 ANSI
            if (PHP_OS_FAMILY === 'Windows') {
                return true;
            }

            // Unix-like 系统
            if (function_exists('posix_isatty') && posix_isatty(STDOUT)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 格式化输出消息
     */
    private function format(string $message): string
    {
        if (!$this->supportsColor) {
            // 只移除颜色标签，保留内容
            return preg_replace('/<\/?[a-z]+>/', '', $message);
        }

        // 处理闭合标签
        $tags = ['error', 'info', 'comment', 'question', 'warn', 'success', 'bold', 'dim', 'italic', 'underline', 'bg=blue;fg=white'];
        foreach ($tags as $tag) {
            $message = str_replace("<{$tag}>", "\033[{$this->getStyleCode($tag)}m", $message);
            $message = str_replace("</{$tag}>", "\033[0m", $message);
        }

        return $message;
    }

    /**
     * 获取样式代码
     */
    private function getStyleCode(string $style): string
    {
        return match ($style) {
            'error', 'red' => '31',
            'info', 'cyan' => '36',
            'comment', 'yellow' => '33',
            'question', 'black' => '30',
            'warn', 'magenta' => '35',
            'success', 'green' => '32',
            'bold' => '1',
            'dim' => '2',
            default => '0'
        };
    }

    /**
     * 写入输出
     */
    public function write(string $message, bool $newline = true): void
    {
        $formatted = $this->format($message) . ($newline ? PHP_EOL : '');

        // 如果输出缓冲激活，使用 echo 以便捕获输出（用于测试）
        if (ob_get_level() > 0) {
            echo $formatted;
        } else {
            fwrite($this->stdout, $formatted);
        }
    }

    /**
     * 写入错误输出
     */
    public function writeError(string $message, bool $newline = true): void
    {
        $formatted = $this->format($message) . ($newline ? PHP_EOL : '');

        // 如果输出缓冲激活，使用 echo 以便捕获输出（用于测试）
        if (ob_get_level() > 0) {
            echo $formatted;
        } else {
            fwrite($this->stderr, $formatted);
        }
    }

    /**
     * 输出行
     */
    public function line(string $message): void
    {
        $this->write($message);
    }

    /**
     * 输出空行
     */
    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->write('');
        }
    }

    /**
     * 输出信息
     */
    public function info(string $message): void
    {
        $this->write("<info>{$message}</info>");
    }

    /**
     * 输出成功消息
     */
    public function success(string $message): void
    {
        $this->write("<success>{$message}</success>");
    }

    /**
     * 输出警告消息
     */
    public function warning(string $message): void
    {
        $this->write("<warn>{$message}</warn>");
    }

    /**
     * 输出错误消息
     */
    public function error(string $message): void
    {
        $this->writeError("<error>{$message}</error>");
    }

    /**
     * 输出注释消息
     */
    public function comment(string $message): void
    {
        $this->write("<comment>{$message}</comment>");
    }

    /**
     * 输出问题
     */
    public function question(string $message): void
    {
        $this->write("<question>{$message}</question>");
    }

    /**
     * 输出标题
     */
    public function title(string $title): void
    {
        $this->newLine();
        $this->line("<bg=blue;fg=white> {$title} </>");
        $this->newLine();
    }

    /**
     * 输出章节
     */
    public function section(string $title): void
    {
        $this->newLine();
        $this->line("<comment>{$title}</comment>");
        $this->newLine();
    }

    /**
     * 输出表格
     */
    public function table(array $headers, array $rows): void
    {
        // 计算每列宽度
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        // 边框字符
        $tl = '┌';
        $tr = '┐';
        $bl = '└';
        $br = '┘';
        $h  = '─';
        $v  = '│';

        // 输出上边框
        $border = $tl;
        foreach ($widths as $width) {
            $border .= str_repeat($h, $width + 2) . '┬';
        }
        $border = substr($border, 0, -1) . $tr;
        $this->line($border);

        // 输出表头
        $headerLine = $v;
        foreach ($headers as $i => $header) {
            $headerLine .= ' ' . str_pad($header, $widths[$i]) . ' ' . $v;
        }
        $this->line($headerLine);

        // 输出分隔线
        $separator = $v;
        foreach ($widths as $width) {
            $separator .= str_repeat($h, $width + 2) . '┼';
        }
        $separator = substr($separator, 0, -1) . $v;
        $this->line($separator);

        // 输出数据行
        foreach ($rows as $row) {
            $line = $v;
            foreach ($row as $i => $cell) {
                $line .= ' ' . str_pad((string) $cell, $widths[$i]) . ' ' . $v;
            }
            $this->line($line);
        }

        // 输出下边框
        $border = $bl;
        foreach ($widths as $width) {
            $border .= str_repeat($h, $width + 2) . '┴';
        }
        $border = substr($border, 0, -1) . $br;
        $this->line($border);
    }

    /**
     * 输出 JSON
     */
    public function json(array $data, int $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): void
    {
        $this->line(json_encode($data, $options));
    }

    /**
     * 输出列表
     */
    public function list(array $items, string $title = null): void
    {
        if ($title !== null) {
            $this->section($title);
        }

        foreach ($items as $key => $value) {
            if (is_string($key)) {
                $this->line("  <info>{$key}:</info> {$value}");
            } else {
                $this->line("  • {$value}");
            }
        }
    }

    /**
     * 输出任务列表
     */
    public function taskList(array $tasks): void
    {
        foreach ($tasks as $task) {
            $status = $task['done'] ?? false ? '<info>✓</info>' : ' ';
            $message = $task['message'] ?? '';
            $this->line("  [{$status}] {$message}");
        }
    }

    /**
     * 确认操作
     */
    public function confirm(string $message, bool $default = false): bool
    {
        $input = new Input();

        $defaultChar = $default ? 'Y' : 'N';
        $response = $input->prompt("{$message} (yes/no) [{$defaultChar}]");

        if ($response === '') {
            return $default;
        }

        return in_array(strtolower($response), ['y', 'yes', '1', 'true'], true);
    }

    /**
     * 询问用户输入
     */
    public function ask(string $question, mixed $default = null): mixed
    {
        $input = new Input();
        return $input->prompt($question, $default);
    }

    /**
     * 选择选项
     */
    public function choice(string $question, array $options, mixed $default = null, bool $multiple = false): mixed
    {
        $input = new Input();

        if ($multiple) {
            return $input->multichoice($question, $options, (array) ($default ?? []));
        }

        return $input->choice($question, $options, $default);
    }

    /**
     * 秘密输入
     */
    public function secret(string $question): string
    {
        $input = new Input();
        return $input->readSecret();
    }

    /**
     * 进度条开始
     */
    public function progressStart(int $max = 100): ProgressBar
    {
        return new ProgressBar($this, $max);
    }

    /**
     * 清屏
     */
    public function clear(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            system('cls');
        } else {
            system('clear');
        }
    }
}
