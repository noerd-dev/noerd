<?php

declare(strict_types=1);

namespace Noerd\Services;

use Noerd\Contracts\ComputedColumnProvider;

/**
 * The providers that compute list columns and detail fields without a stored
 * value. The core registers the model-method provider (`method:`); a package
 * registers its own in its provider's boot():
 *
 *     app(ComputedColumnRegistry::class)->register(MyColumns::class);
 *
 * The first registered provider that handles an item computes it.
 */
class ComputedColumnRegistry
{
    /** @var array<int, class-string<ComputedColumnProvider>> */
    private array $providers = [];

    /** @var array<class-string<ComputedColumnProvider>, ComputedColumnProvider> */
    private array $instances = [];

    /**
     * @param  class-string<ComputedColumnProvider>  $provider
     */
    public function register(string $provider): void
    {
        if (! in_array($provider, $this->providers, true)) {
            $this->providers[] = $provider;
        }
    }

    /**
     * @param  array<string, mixed>  $item  a list column or a detail field
     */
    public function providerFor(array $item): ?ComputedColumnProvider
    {
        foreach ($this->providers as $class) {
            $provider = $this->instances[$class] ??= app($class);
            if ($provider->handles($item)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function isComputed(array $item): bool
    {
        return $this->providerFor($item) !== null;
    }

    /**
     * The computed items grouped by the provider that computes them.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{0: ComputedColumnProvider, 1: array<int, array<string, mixed>>}>
     */
    public function groupByProvider(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $provider = $this->providerFor($item);
            if ($provider === null) {
                continue;
            }

            $id = spl_object_id($provider);
            $groups[$id] ??= [$provider, []];
            $groups[$id][1][] = $item;
        }

        return array_values($groups);
    }
}
