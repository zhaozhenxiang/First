<?php

declare(strict_types=1);

namespace Bin\Foundation\Contracts;

use Bin\App\App;

/**
 * 引导器接口
 *
 * 每个引导器负责应用生命周期中的一个阶段
 */
interface Bootstrapper
{
    /**
     * 引导应用
     */
    public function bootstrap(App $app): void;
}
