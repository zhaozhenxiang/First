<?php

declare(strict_types=1);

namespace Bin\Foundation;

use Bin\App\App;
use Bin\Foundation\Configuration\MiddlewareConfigurator;
use Bin\Foundation\Configuration\RoutingConfigurator;

class ApplicationBuilder
{
    private ApplicationConfiguration $configuration;

    public function __construct(private ?string $basePath = null)
    {
        $basePath ??= defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $this->basePath = $this->normalizeBasePath($basePath);
        $this->configuration = new ApplicationConfiguration($this->basePath);
    }

    /**
     * @param array<class-string>|class-string|null $providers
     */
    public function withProviders(array|string|null $providers = null, ?string $path = null): static
    {
        $providerPath = $path ?? $this->joinBasePath('bootstrap/providers.php');
        $this->configuration->addProviderFile($providerPath);

        if (is_string($providers)) {
            $providers = [$providers];
        }

        if (is_array($providers)) {
            $this->configuration->addProviders($providers);
        }

        return $this;
    }

    /**
     * @param array<int, string> $then
     */
    public function withRouting(?string $web = null, ?string $api = null, array $then = []): static
    {
        $routing = new RoutingConfigurator();

        if ($web === null && $api === null && $then === []) {
            $defaultWeb = $this->joinBasePath('routes/web.php');
            $defaultApi = $this->joinBasePath('routes/api.php');

            if (is_file($defaultWeb)) {
                $routing->add($defaultWeb);
            }

            if (is_file($defaultApi)) {
                $routing->add($defaultApi);
            }
        } else {
            if ($web !== null) {
                $routing->add($web);
            }

            if ($api !== null) {
                $routing->add($api);
            }

            foreach ($then as $file) {
                $routing->add($file);
            }
        }

        foreach ($routing->files() as $file) {
            $this->configuration->addRouteFile($file);
        }

        return $this;
    }

    public function withMiddleware(?callable $callback = null): static
    {
        $middleware = new MiddlewareConfigurator();

        if ($callback !== null) {
            $callback($middleware);
        }

        $this->configuration->setMiddleware($middleware->toArray());

        return $this;
    }

    public function create(): App
    {
        $app = App::getInstance();
        $app->setBasePath($this->basePath);
        $app->setApplicationConfiguration($this->configuration);

        return $app;
    }

    private function normalizeBasePath(string $basePath): string
    {
        $basePath = rtrim($basePath, '/');

        return $basePath === '' ? '/' : $basePath;
    }

    private function joinBasePath(string $path): string
    {
        $basePath = $this->basePath === '/' ? '' : $this->basePath;

        return $basePath . '/' . ltrim($path, '/');
    }
}
