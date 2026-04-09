<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

/**
 * 创建 Console Command 命令
 *
 * php command make:command SendEmails
 */
class MakeCommandCommand extends MakeCommand
{
    public string $signature = 'make:command {name}';
    public string $description = 'Create a new console command';

    protected function getTargetPath(string $name): string
    {
        return $this->getBaseDirectory() . '/' . str_replace('\\', '/', $name) . '.php';
    }

    protected function getStubFile(): string
    {
        return 'command.stub';
    }

    protected function getReplacements(string $name): array
    {
        $className = $this->className($name);
        $commandName = $this->toCommandName($className);

        return array_merge(parent::getReplacements($name), [
            '{{ commandName }}' => $commandName,
        ]);
    }

    protected function getBaseNamespace(): string
    {
        return 'App\\Console\\Commands';
    }

    protected function getBaseDirectory(): string
    {
        return basePath('app/Console/Commands');
    }

    /**
     * 类名转命令名 (SendEmails → send:emails)
     */
    private function toCommandName(string $className): string
    {
        // 移除 Command 后缀
        $name = preg_replace('/Command$/', '', $className) ?? $className;

        // PascalCase → snake_case
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name);

        // 将最后一个下划线改为冒号
        $parts = explode('_', $snake);
        if (count($parts) > 1) {
            $last = array_pop($parts);
            return implode('_', $parts) . ':' . $last;
        }

        return $snake;
    }
}
