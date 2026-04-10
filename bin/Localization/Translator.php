<?php

declare(strict_types=1);

namespace Bin\Localization;

/**
 * 翻译器
 *
 * 管理多语言翻译，支持点号路径查找、参数替换和复数形式。
 *
 * 用法：
 *   Translator::getInstance()->get('messages.welcome');
 *   Translator::getInstance()->get('messages.goodbye', ['name' => 'John']);
 *   Translator::getInstance()->choice('messages.items', 5);
 */
class Translator
{
    protected string $locale = 'en';
    protected string $fallbackLocale = 'en';
    protected array $lines = [];
    protected LanguageLoader $loader;
    protected MessageSelector $selector;

    /** @var self|null 单例 */
    private static ?self $instance = null;

    public function __construct(?LanguageLoader $loader = null)
    {
        $this->loader = $loader ?? new LanguageLoader();
        $this->selector = new MessageSelector();

        if (function_exists('config')) {
            $this->locale = config('app.locale') ?? 'en';
            $this->fallbackLocale = config('app.fallback_locale') ?? 'en';
        }
    }

    /**
     * 获取单例
     */
    public static function getInstance(): static
    {
        return self::$instance ??= new static();
    }

    /**
     * 重置单例
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * 设置当前语言
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * 获取当前语言
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * 设置回退语言
     */
    public function setFallback(string $locale): static
    {
        $this->fallbackLocale = $locale;
        return $this;
    }

    /**
     * 获取回退语言
     */
    public function getFallback(): string
    {
        return $this->fallbackLocale;
    }

    /**
     * 获取翻译
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        $line = $this->getLine($key, $locale);

        if ($line === null && $locale !== $this->fallbackLocale) {
            $line = $this->getLine($key, $this->fallbackLocale);
        }

        if ($line === null) {
            return $this->makeReplacements($key, $replace);
        }

        return $this->makeReplacements($line, $replace);
    }

    /**
     * 获取翻译（复数形式）
     */
    public function choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        $line = $this->getLine($key, $locale);

        if ($line === null && $locale !== $this->fallbackLocale) {
            $line = $this->getLine($key, $this->fallbackLocale);
        }

        if ($line === null) {
            return $this->makeReplacements($key, $replace);
        }

        $line = $this->selector->choose($line, $number, $locale);

        return $this->makeReplacements($line, $replace);
    }

    /**
     * 添加翻译行
     */
    public function addLines(array $lines, string $locale = 'en'): void
    {
        if (!isset($this->lines[$locale])) {
            $this->lines[$locale] = [];
        }

        $this->lines[$locale] = array_merge($this->lines[$locale], $lines);
    }

    /**
     * 添加 JSON 翻译
     */
    public function addJsonTranslations(string $locale, array $lines): void
    {
        if (!isset($this->lines[$locale])) {
            $this->lines[$locale] = [];
        }

        if (!isset($this->lines[$locale]['__json'])) {
            $this->lines[$locale]['__json'] = [];
        }

        $this->lines[$locale]['__json'] = array_merge($this->lines[$locale]['__json'], $lines);
    }

    /**
     * 获取翻译行
     */
    protected function getLine(string $key, string $locale): ?string
    {
        // 确保已加载
        $this->loadLocale($locale);

        // 先尝试 JSON 翻译
        $jsonLines = $this->lines[$locale]['__json'] ?? [];
        if (isset($jsonLines[$key])) {
            return $jsonLines[$key];
        }

        // 点号路径查找
        $segments = explode('.', $key);
        $lines = $this->lines[$locale] ?? [];

        foreach ($segments as $segment) {
            if (is_array($lines) && array_key_exists($segment, $lines)) {
                $lines = $lines[$segment];
            } else {
                return null;
            }
        }

        return is_string($lines) ? $lines : null;
    }

    /**
     * 加载 locale 翻译
     */
    protected function loadLocale(string $locale): void
    {
        if (isset($this->lines[$locale])) {
            return;
        }

        $loaded = $this->loader->load($locale);
        $this->lines[$locale] = $loaded;
    }

    /**
     * 参数替换
     */
    protected function makeReplacements(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        foreach ($replace as $key => $value) {
            $line = str_replace(':' . $key, (string) $value, $line);
        }

        return $line;
    }

    /**
     * 设置 Loader
     */
    public function setLoader(LanguageLoader $loader): static
    {
        $this->loader = $loader;
        return $this;
    }
}
