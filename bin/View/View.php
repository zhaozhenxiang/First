<?php

declare(strict_types=1);

namespace Bin\View;

use Bin\View\Blade\BladeCompiler;

class View
{
    public const string viewPath = BASE_PATH . '/views/';
    public const string viewSuffix = '.php';
    public const string bladeSuffix = '.blade.php';
    public const string cachePath = BASE_PATH . '/storage/views/';

    private static string $targetView;
    private static array $targetData = [];
    private static ?BladeCompiler $bladeCompiler = null;

    public function __construct()
    {
    }

    public static function make($path): self
    {
        // 优先查找 .blade.php
        $bladePath = self::viewPath . $path . self::bladeSuffix;
        $phpPath = self::viewPath . $path . self::viewSuffix;

        if (file_exists($bladePath)) {
            self::$targetView = self::getBladeCompiler()->compile($path . self::bladeSuffix);
        } elseif (file_exists($phpPath)) {
            self::$targetView = $phpPath;
        } elseif (preg_match('/.+?\.php/', $path) && file_exists(self::viewPath . $path)) {
            self::$targetView = self::viewPath . $path;
        } else {
            throw new \RuntimeException("View not found: {$path}");
        }

        self::$targetData = [];

        return new self;
    }

    public function with($key, $value): self
    {
        if (null === self::$targetView) {
            throw new \Exception('WITH must after the MAKE', 1);
        }

        self::$targetData[$key] = $value;

        return $this;
    }

    public function getView(): string
    {
        return self::$targetView;
    }

    public function getData(): array
    {
        return self::$targetData;
    }

    public function __toString(): string
    {
        return (new Compiler($this))->render();
    }

    /**
     * 获取 Blade 编译器实例
     */
    public static function getBladeCompiler(): BladeCompiler
    {
        if (self::$bladeCompiler === null) {
            self::$bladeCompiler = new BladeCompiler(self::viewPath, self::cachePath);
        }

        return self::$bladeCompiler;
    }

    /**
     * 获取共享的 Blade 编译器（用于注册自定义指令等）
     */
    public static function blade(): BladeCompiler
    {
        return self::getBladeCompiler();
    }
}