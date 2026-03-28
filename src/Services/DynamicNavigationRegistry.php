<?php

namespace Noerd\Services;

use Noerd\Contracts\DynamicNavigationProviderContract;

class DynamicNavigationRegistry
{
    /** @var array<string, DynamicNavigationProviderContract> */
    private array $providers = [];

    public function register(DynamicNavigationProviderContract $provider): void
    {
        $this->providers[$provider->type()] = $provider;
    }

    public function resolve(string $type): ?DynamicNavigationProviderContract
    {
        return $this->providers[$type] ?? null;
    }

    /** @return array<string, DynamicNavigationProviderContract> */
    public function all(): array
    {
        return $this->providers;
    }
}
