<?php

declare(strict_types=1);

namespace Bin\Localization;

/**
 * 语言文件加载器
 *
 * 从 lang/ 目录加载 PHP 翻译文件和 JSON 翻译文件。
 */
class LanguageLoader
{
    protected string $path;
    protected array $loaded = [];
    protected array $jsonLoaded = [];

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? $this->getDefaultPath();
    }

    /**
     * 加载指定 locale 的所有翻译
     */
    public function load(string $locale): array
    {
        if (isset($this->loaded[$locale])) {
            return $this->loaded[$locale];
        }

        $lines = [];

        // 加载 PHP 翻译文件
        $localePath = $this->path . '/' . $locale;
        if (is_dir($localePath)) {
            foreach (glob($localePath . '/*.php') as $file) {
                $group = basename($file, '.php');
                $groupLines = $this->loadFile($file);
                if ($groupLines !== []) {
                    $lines[$group] = $groupLines;
                }
            }
        }

        // 加载 JSON 翻译文件
        $jsonTranslations = $this->loadJson($locale);
        if ($jsonTranslations !== []) {
            $lines['__json'] = $jsonTranslations;
        }

        $this->loaded[$locale] = $lines;

        return $lines;
    }

    /**
     * 加载单个 PHP 翻译文件
     */
    public function loadFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $result = require $path;

        return is_array($result) ? $result : [];
    }

    /**
     * 加载 JSON 翻译文件
     */
    public function loadJson(string $locale): array
    {
        if (isset($this->jsonLoaded[$locale])) {
            return $this->jsonLoaded[$locale];
        }

        $jsonPath = $this->path . '/' . $locale . '.json';

        if (!file_exists($jsonPath)) {
            $this->jsonLoaded[$locale] = [];
            return [];
        }

        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);

        $this->jsonLoaded[$locale] = is_array($data) ? $data : [];

        return $this->jsonLoaded[$locale];
    }

    /**
     * 添加自定义命名空间路径
     */
    public function addNamespace(string $namespace, string $path): void
    {
        // 预留扩展
    }

    /**
     * 获取默认语言文件路径
     */
    protected function getDefaultPath(): string
    {
        if (function_exists('base_path')) {
            return base_path('lang');
        }

        return dirname(__DIR__, 2) . '/lang';
    }

    /**
     * 设置路径
     */
    public function setPath(string $path): static
    {
        $this->path = $path;
        $this->loaded = [];
        $this->jsonLoaded = [];
        return $this;
    }

    /**
     * 清除缓存
     */
    public function flush(): void
    {
        $this->loaded = [];
        $this->jsonLoaded = [];
    }
}
