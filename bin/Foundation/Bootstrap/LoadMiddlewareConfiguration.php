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
        $builder = $app->getApplicationConfiguration()->middleware();

        MiddlewareStack::loadFromConfig($this->mergeConfig($legacy, $builder));
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
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    private function mergeConfig(array $legacy, array $builder): array
    {
        return [
            'global' => array_values(array_unique(array_merge($legacy['global'], $builder['global']))),
            'groups' => array_merge($legacy['groups'], $builder['groups']),
            'aliases' => array_merge($legacy['aliases'], $builder['aliases']),
            'priority' => array_merge($legacy['priority'], $builder['priority']),
        ];
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
