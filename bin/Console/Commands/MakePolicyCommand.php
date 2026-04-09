<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

/**
 * 创建 Policy 命令
 *
 * php command make:policy PostPolicy
 */
class MakePolicyCommand extends MakeCommand
{
    public string $signature = 'make:policy {name}';
    public string $description = 'Create a new policy class';

    protected function getTargetPath(string $name): string
    {
        return $this->getBaseDirectory() . '/' . str_replace('\\', '/', $name) . '.php';
    }

    protected function getStubFile(): string
    {
        return 'policy.stub';
    }

    protected function getReplacements(string $name): array
    {
        $className = $this->className($name);

        // PostPolicy → post
        $model = preg_replace('/Policy$/', '', $className) ?? $className;
        $modelVariable = strtolower($model);

        return array_merge(parent::getReplacements($name), [
            '{{ modelVariable }}' => $modelVariable,
        ]);
    }

    protected function getBaseNamespace(): string
    {
        return 'App\\Policies';
    }

    protected function getBaseDirectory(): string
    {
        return basePath('app/Policies');
    }
}
