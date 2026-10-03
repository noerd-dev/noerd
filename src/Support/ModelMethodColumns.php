<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Noerd\Attributes\ComputedValue;
use Noerd\Contracts\ComputedColumnProvider;
use ReflectionMethod;
use Throwable;

/**
 * Computes a list column / detail field with a `method:` key by calling that
 * method on the row's model — e.g. `method: lastOrder` → `Customer::lastOrder()`.
 *
 * Only a public, non-static method without required parameters that carries
 * #[ComputedValue] is called; the check runs by reflection BEFORE the call.
 * Everything else — a typo, `delete`, `save` — renders empty and is logged.
 * The relations the attribute names in `with` are eager-loaded once per page.
 * A method runs in PHP, so its column can be neither sorted nor filtered.
 */
class ModelMethodColumns implements ComputedColumnProvider
{
    /** @var array<string, ComputedValue|null> class::method => marker (null = not callable) */
    private array $markers = [];

    /** @var array<string, true> */
    private array $reported = [];

    public function handles(array $item): bool
    {
        return is_string($item['method'] ?? null) && mb_trim($item['method']) !== '';
    }

    public function prepareQuery(Builder $query, array $columns): void
    {
        $with = $this->eagerLoads($query->getModel()::class, $columns);
        if ($with !== []) {
            $query->with($with);
        }
    }

    public function isSortable(string $modelClass, array $column): bool
    {
        return false;
    }

    public function applyOrder(Builder $query, array $column, string $direction): void {}

    public function isFilterable(string $modelClass, array $column): bool
    {
        return false;
    }

    public function applyFilter(Builder $query, array $column, string $type, string $raw): void {}

    public function fillRows(iterable $rows, array $columns): void
    {
        $models = [];
        foreach ($rows as $row) {
            if ($row instanceof Model) {
                $models[] = $row;
            }
        }

        if ($models === []) {
            return;
        }

        $collection = new EloquentCollection($models);
        $with = $this->eagerLoads($collection->first()::class, $columns);
        if ($with !== []) {
            $collection->loadMissing($with);
        }

        foreach ($collection as $model) {
            $values = [];
            foreach ($columns as $column) {
                $values[$column['field']] = ComputedValuePresenter::present($this->call($model, $column), $column['type'] ?? null);
            }

            // Raw, so no mutator or cast of the model touches a value that is no attribute of it.
            $model->setRawAttributes(array_merge($model->getAttributes(), $values));
        }
    }

    public function detailValues(Model $model, array $fields): array
    {
        if (! $model->exists) {
            return [];
        }

        $with = $this->eagerLoads($model::class, $fields);
        if ($with !== []) {
            $model->loadMissing($with);
        }

        $values = [];
        foreach ($fields as $field) {
            $values[$field['name']] = ComputedValuePresenter::present($this->call($model, $field), $field['type'] ?? null);
        }

        return $values;
    }

    /**
     * The marker of a callable method, or null when the method may not be called.
     */
    public function marker(string $modelClass, string $method): ?ComputedValue
    {
        $key = $modelClass . '::' . $method;
        if (array_key_exists($key, $this->markers)) {
            return $this->markers[$key];
        }

        $marker = null;
        if (method_exists($modelClass, $method)) {
            $reflection = new ReflectionMethod($modelClass, $method);
            $attributes = $reflection->getAttributes(ComputedValue::class);

            if ($reflection->isPublic() && ! $reflection->isStatic() && $reflection->getNumberOfRequiredParameters() === 0 && $attributes !== []) {
                $marker = $attributes[0]->newInstance();
            }
        }

        return $this->markers[$key] = $marker;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function call(Model $model, array $item): mixed
    {
        $method = mb_trim((string) $item['method']);

        if ($this->marker($model::class, $method) === null) {
            $this->report($model::class, $method, 'Computed column method not allowed', 'The method must be public, take no required parameters and carry #[ComputedValue].');

            return null;
        }

        try {
            return $model->{$method}();
        } catch (Throwable $exception) {
            $this->report($model::class, $method, 'Computed column method failed', $exception->getMessage());

            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function eagerLoads(string $modelClass, array $items): array
    {
        $with = [];
        foreach ($items as $item) {
            $marker = $this->marker($modelClass, mb_trim((string) ($item['method'] ?? '')));
            if ($marker !== null) {
                array_push($with, ...$marker->with);
            }
        }

        return array_values(array_unique($with));
    }

    private function report(string $modelClass, string $method, string $message, string $reason): void
    {
        $key = $message . '|' . $modelClass . '::' . $method;
        if (isset($this->reported[$key])) {
            return;
        }

        $this->reported[$key] = true;
        Log::warning($message, ['model' => $modelClass, 'method' => $method, 'reason' => $reason]);
    }
}
