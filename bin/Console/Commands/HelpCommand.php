<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Console\Kernel;

/**
 * Help 命令 - 显示帮助信息
 */
class HelpCommand extends Command
{
    protected string $signature = 'help {command?}';

    protected string $description = 'Display help for a command';

    public function execute(): int
    {
        $commandName = $this->argument('command');

        if ($commandName === null) {
            $this->title('Available Commands');

            $commands = Kernel::getCommands();
            unset($commands['list'], $commands['help']);

            // 按名称分组
            $grouped = [];
            foreach ($commands as $name => $command) {
                $parts = explode(':', $name);
                $group = count($parts) > 1 ? $parts[0] : 'general';

                if (!isset($grouped[$group])) {
                    $grouped[$group] = [];
                }

                $grouped[$group][] = $name;
            }

            ksort($grouped);

            foreach ($grouped as $group => $groupCommands) {
                $this->section(ucfirst($group));

                sort($groupCommands);

                foreach ($groupCommands as $name) {
                    $description = $commands[$name]->getDescription() ?: '';
                    $this->line(sprintf('  <info>%-30s</info> %s', $name, $description));
                }
            }

            $this->newLine();
            $this->comment('Use <info>php command help [command]</info> to show command details.');

            return 0;
        }

        if (!Kernel::hasCommand($commandName)) {
            $this->error("Command not found: {$commandName}");
            return 1;
        }

        $command = Kernel::getCommand($commandName);

        $this->title($commandName);
        $this->line($command->getDescription());

        $this->section('Usage');
        $this->line('  ' . $command->getSignature());

        $arguments = $command->getArguments();
        if (!empty($arguments)) {
            $this->section('Arguments');

            foreach ($arguments as $argName => $definition) {
                $required = $definition['required'] ?? true;
                $array = $definition['array'] ?? false;

                $line = '  <info>' . $argName . '</info>';

                if (!$required) {
                    $line = '[' . $line . ']';
                }

                if ($array) {
                    $line .= '...';
                }

                $this->line($line);
            }
        }

        $options = $command->getOptions();
        if (!empty($options)) {
            $this->section('Options');

            foreach ($options as $optName => $definition) {
                $type = $definition['type'] ?? 'bool';
                $line = '  <info>--' . $optName . '</info>';

                if ($type === 'array') {
                    $line .= '=...';
                } elseif ($type === 'string') {
                    $line .= '=VALUE';
                }

                $this->line($line);
            }
        }

        return 0;
    }
}
