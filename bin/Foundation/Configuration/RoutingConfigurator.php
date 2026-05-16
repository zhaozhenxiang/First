<?php

declare(strict_types=1);

namespace Bin\Foundation\Configuration;

class RoutingConfigurator
{
    /** @var array<int, string> */
    private array $files = [];

    /**
     * @var array<int, array{path: string, type: string}>
     */
    private array $entries = [];

    public function add(string $path, string $type = 'extra'): static
    {
        if (!in_array($path, $this->files, true)) {
            $this->files[] = $path;
            $this->entries[] = [
                'path' => $path,
                'type' => $type,
            ];
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

    /**
     * @return array<int, array{path: string, type: string}>
     */
    public function entries(): array
    {
        return $this->entries;
    }
}
