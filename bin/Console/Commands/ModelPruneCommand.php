<?php

declare(strict_types=1);

namespace Bin\Console\Commands;

use Bin\Console\Command;

/**
 * Model Prune command - clean up models that are no longer needed
 *
 * Discovers Prunable/MassPrunable models under app/Model.
 * Options support comma-separated class name lists (Input repeated options are overwritten):
 *   php command model:prune
 *   php command model:prune --pretend
 *   php command model:prune --model=App\Model\Session
 *   php command model:prune --except=App\Model\Session,App\Model\Log
 */
class ModelPruneCommand extends Command
{
    protected string $signature = 'model:prune {--model=} {--except=} {--pretend}';

    protected string $description = 'Prune models that are no longer needed';

    public function execute(): int
    {
        $pretend = $this->hasOption('pretend');
        $only = array_filter(explode(',', (string) ($this->option('model') ?? '')));
        $except = array_filter(explode(',', (string) ($this->option('except') ?? '')));

        $classes = $this->prunableModels($only, $except);

        if ($classes === []) {
            $this->info('No prunable models found.');

            return 0;
        }

        $total = 0;

        foreach ($classes as $class) {
            $instance = new $class();

            if ($pretend) {
                $count = $instance->prunable()->count();
                $total += $count;
                $this->info("{$class}: {$count} records would be pruned.");
            } else {
                $count = $instance->pruneAll();
                $total += $count;

                if ($count > 0) {
                    $this->info("{$class}: pruned {$count} records.");
                }
            }
        }

        $this->info($pretend
            ? "Total: {$total} records would be pruned."
            : "Total pruned: {$total} records.");

        return 0;
    }

    /**
     * Discover prunable models: scan model dir, filter by --model/--except
     *
     * @param list<string> $only
     * @param list<string> $except
     * @return list<class-string>
     */
    protected function prunableModels(array $only, array $except): array
    {
        $dir = $this->modelPath();

        if (!is_dir($dir)) {
            return [];
        }

        $classes = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            $class = $this->modelNamespace() . str_replace('/', '\\', $relative);

            if (!class_exists($class) || !method_exists($class, 'pruneAll')) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        if ($only !== []) {
            $classes = array_values(array_intersect($classes, $only));
        }

        if ($except !== []) {
            $classes = array_values(array_diff($classes, $except));
        }

        return $classes;
    }

    /**
     * 模型扫描目录（测试可覆写）
     */
    protected function modelPath(): string
    {
        return basePath('app/Model');
    }

    /**
     * 模型命名空间前缀（测试可覆写）
     */
    protected function modelNamespace(): string
    {
        return 'App\\Model\\';
    }
}
