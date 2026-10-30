<?php

declare(strict_types=1);

namespace Noerd\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    /**
     * Let every provider prepare the query for the computed columns it handles.
     *
     * @param  array<int, array<string, mixed>>  $columns
     */
    public function prepareQuery(Builder $query, array $columns): void
    {
        foreach ($this->groupByProvider($columns) as [$provider, $providerColumns]) {
            $provider->prepareQuery($query, $providerColumns);
        }
    }

    /**
     * Compute the columns once for all rows of a page, so every cell reads its
     * value with data_get() like a stored attribute.
     *
     * @param  array<int, array<string, mixed>>  $columns
     */
    public function fillRows(Collection $rows, array $columns): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        foreach ($this->groupByProvider($columns) as [$provider, $providerColumns]) {
            $provider->fillRows($rows, $providerColumns);
        }
    }

    /**
     * Detail values of the computed fields for one record, keyed by field name.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public function detailValues(Model $record, array $fields): array
    {
        $values = [];
        foreach ($this->groupByProvider($fields) as [$provider, $providerFields]) {
            foreach ($provider->detailValues($record, $providerFields) as $name => $value) {
                $values[(string) $name] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  class-string<Model>|null  $modelClass
     * @param  array<string, mixed>  $column
     */
    public function isSortable(?string $modelClass, array $column): bool
    {
        $provider = $this->providerFor($column);

        return $modelClass !== null && $provider !== null && $provider->isSortable($modelClass, $column);
    }

    /**
     * @param  class-string<Model>|null  $modelClass
     * @param  array<string, mixed>  $column
     */
    public function isFilterable(?string $modelClass, array $column): bool
    {
        $provider = $this->providerFor($column);

        return $modelClass !== null && $provider !== null && $provider->isFilterable($modelClass, $column);
    }
}
