<?php

declare(strict_types=1);

namespace Bin\Foundation\Configuration;

class MiddlewareConfigurator
{
    /** @var array<int, string> */
    private array $global = [];

    /** @var array<string, array<int, string>> */
    private array $groups = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, int> */
    private array $priority = [];

    public function append(string $middleware): static
    {
        if (!in_array($middleware, $this->global, true)) {
            $this->global[] = $middleware;
        }

        return $this;
    }

    public function prepend(string $middleware): static
    {
        if (!in_array($middleware, $this->global, true)) {
            array_unshift($this->global, $middleware);
        }

        return $this;
    }

    /**
     * @param array<int, string> $middleware
     */
    public function group(string $name, array $middleware): static
    {
        $this->groups[$name] = array_values(array_unique($middleware));

        return $this;
    }

    /**
     * @param array<int, string> $middleware
     */
    public function web(array $middleware): static
    {
        return $this->group('web', $middleware);
    }

    /**
     * @param array<int, string> $middleware
     */
    public function api(array $middleware): static
    {
        return $this->group('api', $middleware);
    }

    public function appendToGroup(string $group, string $middleware): static
    {
        $this->groups[$group] ??= [];

        if (!in_array($middleware, $this->groups[$group], true)) {
            $this->groups[$group][] = $middleware;
        }

        return $this;
    }

    public function prependToGroup(string $group, string $middleware): static
    {
        $this->groups[$group] ??= [];

        if (!in_array($middleware, $this->groups[$group], true)) {
            array_unshift($this->groups[$group], $middleware);
        }

        return $this;
    }

    public function alias(string $name, string $class): static
    {
        $this->aliases[$name] = $class;

        return $this;
    }

    /**
     * @param array<string, int> $priority
     */
    public function priority(array $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return array{global: array<int, string>, groups: array<string, array<int, string>>, aliases: array<string, string>, priority: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'global' => $this->global,
            'groups' => $this->groups,
            'aliases' => $this->aliases,
            'priority' => $this->priority,
        ];
    }
}
