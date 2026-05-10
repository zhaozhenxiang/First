<?php

declare(strict_types=1);

namespace Bin\Foundation;

class ApplicationConfiguration
{
    /** @var array<class-string> */
    private array $providers = [];

    /** @var array<string> */
    private array $providerFiles = [];

    /** @var array<string> */
    private array $routeFiles = [];

    private bool $hasRouteConfiguration = false;

    /** @var array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>} */
    private array $middleware = [
        'global' => [],
        'groups' => [],
        'aliases' => [],
        'priority' => [],
    ];

    public function __construct(private string $basePath)
    {
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param array<class-string> $providers
     */
    public function addProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            if (is_string($provider) && !in_array($provider, $this->providers, true)) {
                $this->providers[] = $provider;
            }
        }
    }

    /**
     * @return array<class-string>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    public function addProviderFile(string $path): void
    {
        if (!in_array($path, $this->providerFiles, true)) {
            $this->providerFiles[] = $path;
        }
    }

    /**
     * @return array<string>
     */
    public function providerFiles(): array
    {
        return $this->providerFiles;
    }

    public function addRouteFile(string $path): void
    {
        $this->hasRouteConfiguration = true;

        if (!in_array($path, $this->routeFiles, true)) {
            $this->routeFiles[] = $path;
        }
    }

    /**
     * @return array<string>
     */
    public function routeFiles(): array
    {
        return $this->routeFiles;
    }

    public function hasRouteConfiguration(): bool
    {
        return $this->hasRouteConfiguration;
    }

    /**
     * @param array{global?: array<int, string>, groups?: array<string, array<int, string>>, aliases?: array<string, string>, priority?: array<string, int>} $middleware
     */
    public function setMiddleware(array $middleware): void
    {
        $this->middleware = [
            'global' => array_values($middleware['global'] ?? []),
            'groups' => $middleware['groups'] ?? [],
            'aliases' => $middleware['aliases'] ?? [],
            'priority' => $middleware['priority'] ?? [],
        ];
    }

    /**
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    public function middleware(): array
    {
        return $this->middleware;
    }
}
