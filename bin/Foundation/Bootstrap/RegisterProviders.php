<?php

declare(strict_types=1);

namespace Bin\Foundation\Bootstrap;

use Bin\App\App;
use Bin\Foundation\Contracts\Bootstrapper as BootstrapperContract;

/**
 * 注册服务提供者
 *
 * 将应用配置的服务提供者注册到容器中。
 */
class RegisterProviders implements BootstrapperContract
{
    public function bootstrap(App $app): void
    {
        foreach ($this->providers($app) as $provider) {
            $app->register($provider);
        }
    }

    /**
     * @return array<class-string>
     */
    private function providers(App $app): array
    {
        $providers = [];
        $configuration = $app->getApplicationConfiguration();

        $providers = array_merge($providers, $configuration->providers());

        foreach ($configuration->providerFiles() as $providerFile) {
            $providers = array_merge($providers, $this->loadProviderFile($providerFile));
        }

        $providers = array_merge($providers, $this->legacyProviders($app));

        return $this->uniqueProviders($providers);
    }

    /**
     * @return array<class-string>
     */
    private function loadProviderFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $providers = require $path;

        if (!is_array($providers)) {
            throw new \RuntimeException("Provider file [{$path}] must return an array.");
        }

        return array_values(array_filter($providers, 'is_string'));
    }

    /**
     * @return array<class-string>
     */
    private function legacyProviders(App $app): array
    {
        $configPath = $app->configPath('app.php');

        if (!is_file($configPath)) {
            return [];
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \RuntimeException("Config file [{$configPath}] must return an array.");
        }

        return array_values(array_filter($config['providers'] ?? [], 'is_string'));
    }

    /**
     * @param array<int, string> $providers
     * @return array<class-string>
     */
    private function uniqueProviders(array $providers): array
    {
        $unique = [];

        foreach ($providers as $provider) {
            if (!in_array($provider, $unique, true)) {
                $unique[] = $provider;
            }
        }

        return $unique;
    }
}
