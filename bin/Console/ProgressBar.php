<?php

declare(strict_types=1);

namespace Bin\Console;

/**
 * 进度条
 */
class ProgressBar
{
    /** @var Output 输出接口 */
    private Output $output;

    /** @var int 最大值 */
    private int $max;

    /** @var int 当前进度 */
    private int $progress = 0;

    /** @var int 进度条宽度 */
    private int $width = 50;

    /** @var string 字符 */
    private string $barChar = '>';

    /** @var string 背景字符 */
    private string $emptyBarChar = '-';

    /** @var string 进度字符 */
    private string $progressChar = '=';

    /** @var bool 是否显示百分比 */
    private bool $showPercent = true;

    /** @var string|null 消息 */
    private ?string $message = null;

    /** @var int 开始时间 */
    private int $startTime;

    /** @var bool 是否已开始 */
    private bool $started = false;

    /** @var bool 是否已完成 */
    private bool $finished = false;

    /** @var int 最后输出的宽度 */
    private int $lastWidth = 0;

    /**
     * 构造函数
     */
    public function __construct(Output $output, int $max = 100)
    {
        $this->output = $output;
        $this->max = $max;
    }

    /**
     * 开始进度条
     */
    public function start(): self
    {
        $this->started = true;
        $this->startTime = time();
        $this->display();

        return $this;
    }

    /**
     * 设置进度
     */
    public function setProgress(int $step): self
    {
        $this->progress = min($step, $this->max);

        if ($this->started && !$this->finished) {
            $this->display();
        }

        return $this;
    }

    /**
     * 增加进度
     */
    public function advance(int $step = 1): self
    {
        $this->progress = min($this->progress + $step, $this->max);

        if ($this->started && !$this->finished) {
            $this->display();
        }

        return $this;
    }

    /**
     * 设置最大值
     */
    public function setMaxSteps(int $max): self
    {
        $this->max = $max;
        return $this;
    }

    /**
     * 设置消息
     */
    public function setMessage(?string $message): self
    {
        $this->message = $message;

        if ($this->started && !$this->finished) {
            $this->display();
        }

        return $this;
    }

    /**
     * 设置进度条宽度
     */
    public function setWidth(int $width): self
    {
        $this->width = $width;
        return $this;
    }

    /**
     * 设置进度条字符
     */
    public function setBarCharacter(string $char): self
    {
        $this->barChar = $char;
        return $this;
    }

    /**
     * 设置空进度条字符
     */
    public function setEmptyBarCharacter(string $char): self
    {
        $this->emptyBarChar = $char;
        return $this;
    }

    /**
     * 设置进度字符
     */
    public function setProgressCharacter(string $char): self
    {
        $this->progressChar = $char;
        return $this;
    }

    /**
     * 完成进度条
     */
    public function finish(): self
    {
        $this->progress = $this->max;
        $this->finished = true;
        $this->display();
        $this->output->newLine();

        return $this;
    }

    /**
     * 显示进度条
     */
    private function display(): void
    {
        $percent = $this->max > 0 ? ($this->progress / $this->max) : 0;
        $filled = (int) round($this->width * $percent);
        $empty = $this->width - $filled;

        // 构建进度条
        $bar = str_repeat($this->progressChar, $filled);
        if ($filled < $this->width && !$this->finished) {
            $bar .= $this->barChar;
            $empty--;
        }
        $bar .= str_repeat($this->emptyBarChar, max(0, $empty));

        // 构建输出
        $output = "\r";
        $output .= "  ";

        if ($this->message !== null) {
            $output .= $this->message . ' ';
        }

        $output .= "[<info>{$bar}</info>]";

        if ($this->showPercent) {
            $percentage = round($percent * 100);
            $output .= ' ' . $percentage . '%';
        }

        // 添加预计剩余时间
        if ($this->progress > 0 && !$this->finished) {
            $elapsed = time() - $this->startTime;
            $remaining = ($this->max - $this->progress) * ($elapsed / $this->progress);

            if ($remaining > 0) {
                $output .= ' <comment>' . $this->formatTime((int) $remaining) . '</comment>';
            }
        }

        // 添加空白清除之前输出
        $currentWidth = strlen(strip_tags($output));
        if ($currentWidth < $this->lastWidth) {
            $output .= str_repeat(' ', $this->lastWidth - $currentWidth);
        }
        $this->lastWidth = $currentWidth;

        $this->output->write($output, false);
    }

    /**
     * 格式化时间
     */
    private function formatTime(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = (int) ($seconds / 60);
        $seconds = $seconds % 60;

        if ($minutes < 60) {
            return sprintf("%dm %ds", $minutes, $seconds);
        }

        $hours = (int) ($minutes / 60);
        $minutes = $minutes % 60;

        return sprintf("%dh %dm", $hours, $minutes);
    }

    /**
     * 获取当前进度
     */
    public function getProgress(): int
    {
        return $this->progress;
    }

    /**
     * 获取最大值
     */
    public function getMaxSteps(): int
    {
        return $this->max;
    }
}
