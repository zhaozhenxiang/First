<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

/**
 * 创建 FormRequest 命令
 *
 * php command make:request StorePostRequest
 */
class MakeRequestCommand extends MakeCommand
{
    public string $signature = 'make:request {name}';
    public string $description = 'Create a new form request class';

    protected function getTargetPath(string $name): string
    {
        return $this->getBaseDirectory() . '/' . str_replace('\\', '/', $name) . '.php';
    }

    protected function getStubFile(): string
    {
        return 'request.stub';
    }

    protected function getBaseNamespace(): string
    {
        return 'App\\Requests';
    }

    protected function getBaseDirectory(): string
    {
        return basePath('app/Requests');
    }

    protected function afterCreate(string $name, string $path): void
    {
        $this->newLine();
        $this->comment('Use in controller:');
        $this->line("  public function store(\\{$this->getBaseNamespace()}\\{$name} \$request)");
    }
}
