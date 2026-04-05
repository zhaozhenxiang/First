<?php

declare(strict_types=1);

namespace Bin\View;

class View
{
    public const string viewPath = BASE_PATH . '/views/';
    public const string viewSuffix = '.php';
    private static string $targetView;
    private static array $targetData = [];
    public function __construct()
    {
    }

    public static function make($path): self
    {
        if (false == preg_match('/.+?\.php/', $path)) {
            $path .= self::viewSuffix;
        }

        self::$targetView = self::viewPath . $path;

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

    /**
     *
     * @return string
     */
    public function getView(): string
    {
        return self::$targetView;
    }

    public function getData(): array
    {
        return self::$targetData;
    }

    /**
     *
     * @return string
     * @throws \Exception
     */
    public function __toString()
    {
        return (new Compiler($this))->getPHP();
    }
}