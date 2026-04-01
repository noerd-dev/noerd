<?php

namespace Noerd\Services;

class PicklistRegistry
{
    /** @var array<string, callable> */
    private array $providers = [];

    public function register(string $name, callable $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    public function resolve(string $name): ?callable
    {
        return $this->providers[$name] ?? null;
    }
}
