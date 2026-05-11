<?php

declare(strict_types=1);

namespace Bin\Foundation\Configuration;

class MiddlewareConfigurator
{
    /** @var array<int, string> */
    private array $global = [];

    /** @var array<int, string> */
    private array $globalPrepends = [];

    /** @var array<int, string> */
    private array $globalAppends = [];

    /** @var array<string, array<int, string>> */
    private array $groups = [];

    /** @var array<string, array<int, string>> */
    private array $groupReplacements = [];

    /** @var array<string, array<int, string>> */
    private array $groupPrepends = [];

    /** @var array<string, array<int, string>> */
    private array $groupAppends = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, int> */
    private array $priority = [];

    public function append(string $middleware): static
    {
        if (!in_array($middleware, $this->global, true)) {
            $this->global[] = $middleware;
            $this->globalAppends[] = $middleware;
        }

        return $this;
    }

    public function prepend(string $middleware): static
    {
        if (!in_array($middleware, $this->global, true)) {
            array_unshift($this->global, $middleware);
            array_unshift($this->globalPrepends, $middleware);
        }

        return $this;
    }

    /**
     * @param array<int, string> $middleware
     */
    public function group(string $name, array $middleware): static
    {
        $this->groupReplacements[$name] = array_values(array_unique($middleware));
        unset($this->groupPrepends[$name], $this->groupAppends[$name]);
        $this->refreshGroup($name);

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
        $this->refreshGroup($group);

        if (!in_array($middleware, $this->groups[$group], true)) {
            $this->groupAppends[$group] ??= [];
            $this->groupAppends[$group][] = $middleware;
            $this->refreshGroup($group);
        }

        return $this;
    }

    public function prependToGroup(string $group, string $middleware): static
    {
        $this->refreshGroup($group);

        if (!in_array($middleware, $this->groups[$group], true)) {
            $this->groupPrepends[$group] ??= [];
            array_unshift($this->groupPrepends[$group], $middleware);
            $this->refreshGroup($group);
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

    /**
     * @return array{
     *     global_prepend: array<int, string>,
     *     global_append: array<int, string>,
     *     group_replace: array<string, array<int, string>>,
     *     group_prepend: array<string, array<int, string>>,
     *     group_append: array<string, array<int, string>>
     * }
     */
    public function operations(): array
    {
        return [
            'global_prepend' => $this->globalPrepends,
            'global_append' => $this->globalAppends,
            'group_replace' => $this->groupReplacements,
            'group_prepend' => $this->groupPrepends,
            'group_append' => $this->groupAppends,
        ];
    }

    private function refreshGroup(string $group): void
    {
        $this->groups[$group] = array_values(array_unique(array_merge(
            $this->groupPrepends[$group] ?? [],
            $this->groupReplacements[$group] ?? [],
            $this->groupAppends[$group] ?? []
        )));
    }
}
