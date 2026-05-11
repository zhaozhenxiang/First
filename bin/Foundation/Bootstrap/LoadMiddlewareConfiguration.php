<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;
use Bin\Middleware\MiddlewareStack;
use RuntimeException;

class LoadMiddlewareConfiguration implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        $legacy = $this->legacyConfig($app);
        $configuration = $app->getApplicationConfiguration();
        $builder = $configuration->middleware();
        $operations = $configuration->middlewareOperations();

        MiddlewareStack::loadFromConfig($this->mergeConfig($legacy, $builder, $operations));
    }

    /**
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    private function legacyConfig(App $app): array
    {
        $configPath = $app->configPath('middleware.php');

        if (!file_exists($configPath)) {
            return $this->emptyConfig();
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new RuntimeException("Middleware configuration file must return an array: {$configPath}");
        }

        return [
            'global' => array_values($config['global'] ?? []),
            'groups' => $config['groups'] ?? [],
            'aliases' => $config['aliases'] ?? [],
            'priority' => $config['priority'] ?? [],
        ];
    }

    /**
     * @param array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>} $legacy
     * @param array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>} $builder
     * @param array{
     *     global_prepend: array<int, string>,
     *     global_append: array<int, string>,
     *     group_replace: array<string, array<int, string>>,
     *     group_prepend: array<string, array<int, string>>,
     *     group_append: array<string, array<int, string>>
     * } $operations
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    private function mergeConfig(array $legacy, array $builder, array $operations): array
    {
        return [
            'global' => $this->mergeGlobalMiddleware($legacy['global'], $builder['global'], $operations),
            'groups' => $this->mergeGroups($legacy['groups'], $builder['groups'], $operations),
            'aliases' => array_merge($legacy['aliases'], $builder['aliases']),
            'priority' => array_merge($legacy['priority'], $builder['priority']),
        ];
    }

    /**
     * @param array<int, string> $legacy
     * @param array<int, string> $builder
     * @param array{
     *     global_prepend: array<int, string>,
     *     global_append: array<int, string>,
     *     group_replace: array<string, array<int, string>>,
     *     group_prepend: array<string, array<int, string>>,
     *     group_append: array<string, array<int, string>>
     * } $operations
     * @return array<int, string>
     */
    private function mergeGlobalMiddleware(array $legacy, array $builder, array $operations): array
    {
        $hasOperationMetadata = $operations['global_prepend'] !== [] || $operations['global_append'] !== [];
        $append = $hasOperationMetadata ? $operations['global_append'] : $builder;

        return $this->uniqueList(array_merge($operations['global_prepend'], $legacy, $append));
    }

    /**
     * @param array<string, array<int, string>> $legacy
     * @param array<string, array<int, string>> $builder
     * @param array{
     *     global_prepend: array<int, string>,
     *     global_append: array<int, string>,
     *     group_replace: array<string, array<int, string>>,
     *     group_prepend: array<string, array<int, string>>,
     *     group_append: array<string, array<int, string>>
     * } $operations
     * @return array<string, array<int, string>>
     */
    private function mergeGroups(array $legacy, array $builder, array $operations): array
    {
        $hasOperationMetadata = $operations['group_replace'] !== []
            || $operations['group_prepend'] !== []
            || $operations['group_append'] !== [];
        $replacements = $hasOperationMetadata ? $operations['group_replace'] : $builder;
        $groupNames = array_unique(array_merge(
            array_keys($legacy),
            array_keys($replacements),
            array_keys($operations['group_prepend']),
            array_keys($operations['group_append'])
        ));

        $groups = [];

        foreach ($groupNames as $group) {
            $base = array_key_exists($group, $replacements) ? $replacements[$group] : ($legacy[$group] ?? []);
            $groups[$group] = $this->uniqueList(array_merge(
                $operations['group_prepend'][$group] ?? [],
                $base,
                $operations['group_append'][$group] ?? []
            ));
        }

        return $groups;
    }

    /**
     * @param array<int, string> $items
     * @return array<int, string>
     */
    private function uniqueList(array $items): array
    {
        return array_values(array_unique($items));
    }

    /**
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    private function emptyConfig(): array
    {
        return [
            'global' => [],
            'groups' => [],
            'aliases' => [],
            'priority' => [],
        ];
    }
}
