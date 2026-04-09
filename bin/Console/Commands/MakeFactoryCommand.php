<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

/**
 * 创建 Model Factory 命令
 *
 * php command make:factory PostFactory
 */
class MakeFactoryCommand extends MakeCommand
{
    public string $signature = 'make:factory {name}';
    public string $description = 'Create a new model factory';

    protected function getTargetPath(string $name): string
    {
        $dir = basePath('database/factories');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . '/' . $this->className($name) . '.php';
    }

    protected function getStubFile(): string
    {
        return 'factory.stub';
    }

    protected function getReplacements(string $name): array
    {
        $className = $this->className($name);

        // PostFactory → Post
        $model = preg_replace('/Factory$/', '', $className) ?? $className;

        return array_merge(parent::getReplacements($name), [
            '{{ model }}' => $model,
        ]);
    }

    protected function getBaseNamespace(): string
    {
        return 'Database\\Factories';
    }
}
