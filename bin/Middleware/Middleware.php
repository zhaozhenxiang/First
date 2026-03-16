<?php

declare(strict_types=1);

namespace Bin\Middleware;

abstract class Middleware
{

    /**
     *  获取上下文
     */
    protected function getContext()
    {
        return;
    }

    /**
     * 该函数函数返回true该可以继续
     * @return mixed|bool
     */
    abstract protected function handle(array $param);

    /**
     * 调用入口
     * @return mixed
     */
    public function run(array $param): mixed
    {
        $result = $this->handle($param);

        // 返回 true 表示继续执行，其他值作为响应返回
        if ($result === true) {
            return true;
        }

        return $result;
    }
}