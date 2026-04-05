<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;
use Bin\Config\ConfigRepository;

/**
 * ConfigCache 命令 - 编译配置文件到 storage/config/
 */
class ConfigCacheCommand extends Command
{
    protected string $signature = 'config:cache';

    protected string $description = 'Compile config files into storage/config/ for performance';

    public function execute(): int
    {
        $configPath = basePath('/config');
        $compiledPath = basePath('/storage/config');

        if (!is_dir($configPath)) {
            $this->error('Config directory not found: ' . $configPath);
            return 1;
        }

        // 创建编译目录
        if (!is_dir($compiledPath)) {
            mkdir($compiledPath, 0755, true);
        }

        $files = glob($configPath . '/*.php');

        if (empty($files)) {
            $this->warning('No config files found.');
            return 0;
        }

        $count = 0;
        foreach ($files as $file) {
            $name = basename($file, '.php');

            // require 源文件（此时 env() 已可用）
            $config = require $file;

            if (!is_array($config)) {
                $this->warning("Skipping {$name}: does not return an array.");
                continue;
            }

            // 导出为纯数组 PHP 文件
            $content = $this->export($config);
            $target = $compiledPath . '/' . $name . '.php';

            file_put_contents($target, $content);
            $count++;
            $this->info("  Compiled: {$name}");
        }

        $this->success("Config cache generated ({$count} files).");

        return 0;
    }

    /**
     * 导出配置数组为 PHP 文件内容
     */
    private function export(array $config): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $this->varExport($config) . ";\n";
    }

    /**
     * 美化导出变量
     */
    private function varExport(mixed $var, int $depth = 0): string
    {
        $indent = str_repeat('    ', $depth);
        $innerIndent = str_repeat('    ', $depth + 1);

        if (is_array($var)) {
            $indexed = array_keys($var) === range(0, count($var) - 1);
            $r = [];

            foreach ($var as $key => $value) {
                $value = $this->varExport($value, $depth + 1);

                if ($indexed) {
                    $r[] = $value;
                } else {
                    $r[] = "'" . $key . "' => " . $value;
                }
            }

            if (empty($r)) {
                return '[]';
            }

            return "[\n" . $innerIndent . implode(",\n" . $innerIndent, $r) . "\n" . $indent . ']';
        }

        if ($var === true) {
            return 'true';
        }
        if ($var === false) {
            return 'false';
        }
        if ($var === null) {
            return 'null';
        }
        if (is_string($var)) {
            return "'" . addslashes($var) . "'";
        }
        if (is_int($var) || is_float($var)) {
            return (string) $var;
        }

        return var_export($var, true);
    }
}
