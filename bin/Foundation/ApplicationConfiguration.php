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

    /**
     * @var array<int, array{path: string, type: string}>
     */
    private array $routeFileEntries = [];

    private bool $hasRouteConfiguration = false;

    /** @var array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>} */
    private array $middleware = [
        'global' => [],
        'groups' => [],
        'aliases' => [],
        'priority' => [],
    ];

    /**
     * @var array{
     *     global_prepend: array<int, string>,
     *     global_append: array<int, string>,
     *     group_replace: array<string, array<int, string>>,
     *     group_prepend: array<string, array<int, string>>,
     *     group_append: array<string, array<int, string>>
     * }
     */
    private array $middlewareOperations = [
        'global_prepend' => [],
        'global_append' => [],
        'group_replace' => [],
        'group_prepend' => [],
        'group_append' => [],
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

    public function addRouteFile(string $path, string $type = 'extra'): void
    {
        $this->hasRouteConfiguration = true;

        if (!in_array($path, $this->routeFiles, true)) {
            $this->routeFiles[] = $path;
        }

        foreach ($this->routeFileEntries as $entry) {
            if ($entry['path'] === $path) {
                return;
            }
        }

        $this->routeFileEntries[] = [
            'path' => $path,
            'type' => $type,
        ];
    }

    /**
     * @return array<string>
     */
    public function routeFiles(): array
    {
        return $this->routeFiles;
    }

    /**
     * @return array<int, array{path: string, type: string}>
     */
    public function routeFileEntries(): array
    {
        return $this->routeFileEntries;
    }

    public function hasRouteConfiguration(): bool
    {
        return $this->hasRouteConfiguration;
    }

    /**
     * @param array{global?: array<int, string>, groups?: array<string, array<int, string>>, aliases?: array<string, string>, priority?: array<string, int>} $middleware
     * @param array{
     *     global_prepend?: array<int, string>,
     *     global_append?: array<int, string>,
     *     group_replace?: array<string, array<int, string>>,
     *     group_prepend?: array<string, array<int, string>>,
     *     group_append?: array<string, array<int, string>>
     * } $operations
     */
    public function setMiddleware(array $middleware, array $operations = []): void
    {
        $this->middleware = [
            'global' => array_values($middleware['global'] ?? []),
            'groups' => $middleware['groups'] ?? [],
            'aliases' => $middleware['aliases'] ?? [],
            'priority' => $middleware['priority'] ?? [],
        ];

        $defaultGroupReplacements = $operations === [] ? $this->middleware['groups'] : [];
        $this->middlewareOperations = [
            'global_prepend' => array_values($operations['global_prepend'] ?? []),
            'global_append' => array_values($operations['global_append'] ?? []),
            'group_replace' => $operations['group_replace'] ?? $defaultGroupReplacements,
            'group_prepend' => $operations['group_prepend'] ?? [],
            'group_append' => $operations['group_append'] ?? [],
        ];
    }

    /**
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    public function middleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return array{
     *     global_prepend: array<int, string>,
     *     global_append: array<int, string>,
     *     group_replace: array<string, array<int, string>>,
     *     group_prepend: array<string, array<int, string>>,
     *     group_append: array<string, array<int, string>>
     * }
     */
    public function middlewareOperations(): array
    {
        return $this->middlewareOperations;
    }
}
