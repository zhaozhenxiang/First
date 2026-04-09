<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

/**
 * 创建 Middleware 命令
 *
 * php command make:middleware AuthMiddleware
 */
class MakeMiddlewareCommand extends MakeCommand
{
    public string $signature = 'make:middleware {name}';
    public string $description = 'Create a new middleware class';

    protected function getTargetPath(string $name): string
    {
        return $this->getBaseDirectory() . '/' . str_replace('\\', '/', $name) . '.php';
    }

    protected function getStubFile(): string
    {
        return 'middleware.stub';
    }

    protected function getBaseNamespace(): string
    {
        return 'App\\Middleware';
    }

    protected function getBaseDirectory(): string
    {
        return basePath('app/Middleware');
    }
}
