<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

/**
 * 创建 Observer 命令
 *
 * php command make:observer PostObserver
 */
class MakeObserverCommand extends MakeCommand
{
    public string $signature = 'make:observer {name}';
    public string $description = 'Create a new model observer class';

    protected function getTargetPath(string $name): string
    {
        return $this->getBaseDirectory() . '/' . str_replace('\\', '/', $name) . '.php';
    }

    protected function getStubFile(): string
    {
        return 'observer.stub';
    }

    protected function getReplacements(string $name): array
    {
        $className = $this->className($name);

        // PostObserver → post
        $model = preg_replace('/Observer$/', '', $className) ?? $className;
        $modelVariable = strtolower($model);

        return array_merge(parent::getReplacements($name), [
            '{{ modelVariable }}' => $modelVariable,
        ]);
    }

    protected function getBaseNamespace(): string
    {
        return 'App\\Observers';
    }

    protected function getBaseDirectory(): string
    {
        return basePath('app/Observers');
    }
}
