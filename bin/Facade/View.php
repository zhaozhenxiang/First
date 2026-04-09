<?php

declare(strict_types=1);

namespace Bin\Facade;

/**
 * View Facade - 静态代理视图系统
 *
 * @method static \Bin\View\View make(string $path)
 */
class View extends Facade
{
    protected function getClassName(): string
    {
        return \Bin\View\View::class;
    }

    /**
     * 创建视图实例
     */
    public static function make(string $path): \Bin\View\View
    {
        return \Bin\View\View::make($path);
    }
}
