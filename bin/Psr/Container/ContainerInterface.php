<?php

declare(strict_types=1);

namespace Bin\Psr\Container;

/**
 * PSR-11 容器接口
 *
 * 描述依赖注入容器的标准接口
 */
interface ContainerInterface
{
    /**
     * 从容器中获取条目
     *
     * @param string $id 条目标识符
     * @return mixed 条目实例
     * @throws NotFoundExceptionInterface 条目不存在时
     * @throws ContainerExceptionInterface 其他错误时
     */
    public function get(string $id): mixed;

    /**
     * 检查容器中是否有指定条目
     *
     * @param string $id 条目标识符
     */
    public function has(string $id): bool;
}
