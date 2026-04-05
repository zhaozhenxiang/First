<?php

declare(strict_types=1);

if (!function_exists('app')) {
    /**
     * 从 IoC 容器解析实例
     */
    function app(string $class): object
    {
        return \Bin\App\App::getInstance()->make($class);
    }
}
