<?php

declare(strict_types=1);

namespace Bin\Contracts;

/**
 * 视图接口
 */
interface ViewInterface
{
    /**
     * 创建一个视图实例
     */
    public static function make(string $template): self;

    /**
     * 向视图传递数据
     */
    public function with(string $key, mixed $value): self;

    /**
     * 批量传递数据
     */
    public function withArray(array $data): self;

    /**
     * 渲染视图
     */
    public function render(): string;

    /**
     * 获取模板路径
     */
    public function getPath(): string;
}
