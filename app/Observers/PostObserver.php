<?php

declare(strict_types=1);

namespace App\Observers;

use Bin\Database\Observer;

/**
 * PostObserver 模型观察者
 */
class PostObserver extends Observer
{
    /**
     * 模型创建后
     */
    public function created(object $post): void
    {
        //
    }

    /**
     * 模型更新后
     */
    public function updated(object $post): void
    {
        //
    }

    /**
     * 模型删除后
     */
    public function deleted(object $post): void
    {
        //
    }
}
