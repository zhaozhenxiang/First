<?php

declare(strict_types=1);

namespace Bin\Console;

use Closure;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * 命令调度器
 */
class Kernel
{
    /** @var array<string, Command> 已注册的命令 */
    private static array $commands = [];

    /** @var array<string, Closure> 命令工厂 */
    private static array $factories = [];

    /** @var array<string, string> 命令别名 */
    private static array $aliases = [];

    /** @var string 命令搜索路径 */
    private static array $paths = [];

    /** @var string 应用命名空间 */
    private static string $appNamespace = 'App\\Console\\';

    /**
     * 注册命令
     */
    public static function register(string $name, string|Closure|Command $command): void
    {
        if ($command instanceof Closure) {
            self::$factories[$name] = $command;
        } elseif ($command instanceof Command) {
            self::$commands[$name] = $command;
        } else {
            // 如果是类名，尝试实例化
            if (class_exists($command)) {
                self::$commands[$name] = new $command();
            } else {
                // 存储类名，延迟实例化
                self::$factories[$name] = fn() => new $command();
            }
        }
    }

    /**
     * 批量注册命令
     */
    public static function registerCommands(array $commands): void
    {
        foreach ($commands as $name => $command) {
            self::register($name, $command);
        }
    }

    /**
     * 设置命令别名
     * @param string $alias 别名
     * @param string $command 目标命令名称
     */
    public static function alias(string $alias, string $command): void
    {
        self::$aliases[$alias] = $command;
    }

    /**
     * 设置搜索路径
     */
    public static function addPath(string $path): void
    {
        self::$paths[] = $path;
    }

    /**
     * 设置应用命名空间
     */
    public static function setAppNamespace(string $namespace): void
    {
        self::$appNamespace = $namespace;
    }

    /**
     * 自动发现命令
     */
    public static function discover(): void
    {
        // 默认搜索路径
        $paths = array_merge([
            basePath('bin/Console/Commands'),
            basePath('app/Console/Commands'),
        ], self::$paths);

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = glob($path . '/*Command.php');

            foreach ($files as $file) {
                $className = self::pathToClassName($file);

                if (!class_exists($className)) {
                    require_once $file;
                }

                try {
                    $reflection = new ReflectionClass($className);

                    if ($reflection->isSubclassOf(Command::class) && !$reflection->isAbstract()) {
                        $instance = $reflection->newInstance();
                        $instance->parseSignature();

                        $name = $instance->getName();
                        if ($name !== '') {
                            self::$commands[$name] = $instance;
                        }
                    }
                } catch (ReflectionException) {
                    continue;
                }
            }
        }
    }

    /**
     * 路径转类名
     */
    private static function pathToClassName(string $path): string
    {
        $relativePath = str_replace([basePath(), '.php'], ['', ''], $path);

        // 框架命令直接用 Bin\ 命名空间
        if (str_starts_with($relativePath, '/bin/')) {
            return str_replace('/', '\\', substr($relativePath, 1));
        }

        // 应用命令使用 App 命名空间
        $className = self::$appNamespace . 'Commands\\' . str_replace('/', '\\', ltrim($relativePath, '/'));

        return $className;
    }

    /**
     * 获取命令实例
     */
    public static function getCommand(string $name): Command
    {
        // 检查别名
        if (isset(self::$aliases[$name])) {
            $name = self::$aliases[$name];
        }

        // 从工厂创建
        if (isset(self::$factories[$name])) {
            return self::$factories[$name]();
        }

        // 返回已注册的命令
        if (isset(self::$commands[$name])) {
            return self::$commands[$name];
        }

        throw new RuntimeException("Command not found: {$name}");
    }

    /**
     * 检查命令是否存在
     */
    public static function hasCommand(string $name): bool
    {
        if (isset(self::$aliases[$name])) {
            $name = self::$aliases[$name];
        }

        return isset(self::$commands[$name]) || isset(self::$factories[$name]);
    }

    /**
     * 获取所有命令
     */
    public static function getCommands(): array
    {
        self::discover();

        // 实例化所有工厂命令
        $commands = self::$commands;
        foreach (array_keys(self::$factories) as $name) {
            if (!isset($commands[$name])) {
                try {
                    $commands[$name] = self::getCommand($name);
                } catch (\Throwable $e) {
                    // 忽略无法实例化的命令
                }
            }
        }

        return $commands;
    }

    /**
     * 获取所有命令名称
     */
    public static function getCommandNames(): array
    {
        return array_keys(self::getCommands());
    }

    /**
     * 调用命令
     */
    public static function call(string $command, array $arguments = []): int
    {
        return self::callSilent($command, $arguments);
    }

    /**
     * 静默调用命令（不输出）
     */
    public static function callSilent(string $command, array $arguments = []): int
    {
        // 临时捕获输出
        ob_start();

        try {
            $instance = self::getCommand($command);
            $instance->parseSignature();

            // 构造输入
            $argv = ['script', $command, ...$arguments];
            $input = new Input($argv);

            // 构造输出
            $output = new Output();

            // 执行命令
            $exitCode = $instance->run($input, $output);

            return $exitCode;
        } catch (RuntimeException $e) {
            return 1;
        } finally {
            ob_end_clean();
        }
    }

    /**
     * 处理 CLI 请求
     */
    public static function handle(): int
    {
        self::discover();

        $input = new Input();
        $output = new Output();

        $commandName = $input->getCommandName();

        if ($commandName === '' || $commandName === 'help') {
            return self::showHelp($input, $output);
        }

        if ($commandName === 'list') {
            return self::showList($output);
        }

        try {
            $command = self::getCommand($commandName);
            $command->parseSignature();

            return $command->run($input, $output);
        } catch (RuntimeException $e) {
            $output->error("<error>{$e->getMessage()}</error>");
            return 1;
        }
    }

    /**
     * 显示帮助信息
     */
    private static function showHelp(Input $input, Output $output): int
    {
        $commandName = $input->getArgument(1);

        if ($commandName !== null && self::hasCommand($commandName)) {
            return self::showCommandHelp($commandName, $output);
        }

        $output->title('Available Commands');

        $commands = self::getCommands();

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
            $output->section(ucfirst($group));

            sort($groupCommands);

            foreach ($groupCommands as $name) {
                $description = $commands[$name]->getDescription() ?: '';
                $output->line(sprintf('  <info>%-30s</info> %s', $name, $description));
            }
        }

        $output->newLine();
        $output->comment('Use <info>php command help [command]</info> to show command details.');

        return 0;
    }

    /**
     * 显示单个命令的帮助
     */
    private static function showCommandHelp(string $name, Output $output): int
    {
        $command = self::getCommand($name);

        $output->title($name);
        $output->line($command->getDescription());

        $output->section('Usage');
        $output->line('  ' . $command->getSignature());

        $arguments = $command->getArguments();
        if (!empty($arguments)) {
            $output->section('Arguments');
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

                $output->line($line);
            }
        }

        $options = $command->getOptions();
        if (!empty($options)) {
            $output->section('Options');
            foreach ($options as $optName => $definition) {
                $type = $definition['type'] ?? 'bool';
                $line = '  <info>--' . $optName . '</info>';

                if ($type === 'array') {
                    $line .= '=...';
                } elseif ($type === 'string') {
                    $line .= '=VALUE';
                }

                $output->line($line);
            }
        }

        return 0;
    }

    /**
     * 显示命令列表
     */
    private static function showList(Output $output): int
    {
        $commands = self::getCommands();

        $rows = [];
        foreach ($commands as $name => $command) {
            $rows[] = [$name, $command->getDescription() ?: ''];
        }

        $output->table(['Command', 'Description'], $rows);

        return 0;
    }

    /**
     * 清除所有命令
     */
    public static function clear(): void
    {
        self::$commands = [];
        self::$factories = [];
        self::$aliases = [];
        self::$paths = [];
    }

    /**
     * 获取应用命名空间
     */
    public static function getAppNamespace(): string
    {
        return self::$appNamespace;
    }
}
