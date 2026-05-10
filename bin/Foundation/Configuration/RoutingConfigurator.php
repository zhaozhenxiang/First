<?php

declare(strict_types=1);

namespace Bin\Foundation\Configuration;

class RoutingConfigurator
{
    /** @var array<int, string> */
    private array $files = [];

    public function add(string $path): static
    {
        if (!in_array($path, $this->files, true)) {
            $this->files[] = $path;
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function files(): array
    {
        return $this->files;
    }
}
