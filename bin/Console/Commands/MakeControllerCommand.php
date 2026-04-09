<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

/**
 * 创建 Controller 命令
 *
 * php command make:controller PostController
 * php command make:controller PostController --resource
 * php command make:controller PostController --api
 * php command make:controller PostController --invokable
 */
class MakeControllerCommand extends MakeCommand
{
    public string $signature = 'make:controller {name} {--resource} {--api} {--invokable}';
    public string $description = 'Create a new controller class';

    protected function getTargetPath(string $name): string
    {
        return $this->getBaseDirectory() . '/' . str_replace('\\', '/', $name) . '.php';
    }

    protected function getStubFile(): string
    {
        if ($this->hasOption('api')) {
            return 'controller.api.stub';
        }
        if ($this->hasOption('invokable')) {
            return 'controller.invokable.stub';
        }
        if ($this->hasOption('resource')) {
            return 'controller.stub';
        }
        return 'controller.stub';
    }

    protected function getBaseNamespace(): string
    {
        return 'App\\Controllers';
    }

    protected function getBaseDirectory(): string
    {
        return basePath('app/Controllers');
    }

    protected function afterCreate(string $name, string $path): void
    {
        $className = $this->className($name);
        $this->newLine();
        $this->comment('Register in routes:');
        $this->line("  Route::get('/path', [\\{$this->getBaseNamespace()}\\{$name}::class, 'method']);");
    }
}
