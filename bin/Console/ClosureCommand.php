<?php

declare(strict_types=1);

namespace Bin\Console;

use Closure;

/**
 * 闭包命令 — 将闭包包装为 Command 实例
 *
 * 用法:
 *   Kernel::command('greet {name}', function (string $name) {
 *       $this->info("Hello, {$name}!");
 *   });
 */
class ClosureCommand extends Command
{
    /**
     * 命令执行闭包
     */
    private Closure $callback;

    /**
     * @param string $signature 命令签名（包含名称）
     * @param Closure $callback 执行闭包，参数按签名定义的参数名注入
     * @param string $description 命令描述
     */
    public function __construct(string $signature, Closure $callback, string $description = '')
    {
        $this->signature = $signature;
        $this->callback = $callback;
        $this->description = $description;
    }

    /**
     * 执行命令
     */
    public function execute(): int
    {
        // 收集签名定义的参数名，按顺序从 argument() 获取值
        $argNames = array_keys($this->arguments);
        $args = [];
        foreach ($argNames as $name) {
            $args[] = $this->argument($name);
        }

        $result = ($this->callback)(...$args);

        // 闭包返回 int 作为退出码，null/void 视为成功
        if ($result === null) {
            return 0;
        }

        return is_int($result) ? $result : 0;
    }

    /**
     * 获取闭包
     */
    public function getCallback(): Closure
    {
        return $this->callback;
    }
}
