<?php

declare(strict_types=1);

namespace Noerd\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Computes the value of a list column or detail field that names no stored
 * value. Providers are registered on the ComputedColumnRegistry; each one
 * recognises its own YAML key (`handles()`). The core ships the model-method
 * provider (`method:`); packages may add further providers.
 */
interface ComputedColumnProvider
{
    /**
     * Whether this provider computes the given list column / detail field.
     *
     * @param  array<string, mixed>  $item
     */
    public function handles(array $item): bool;

    /**
     * Prepare a list query for the given columns (eager loads, subselects)
     * before the rows are fetched. Must be idempotent: a CSV export may
     * prepare a query listQuery() prepared already.
     *
     * @param  array<int, array<string, mixed>>  $columns
     */
    public function prepareQuery(Builder $query, array $columns): void;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $column
     */
    public function isSortable(string $modelClass, array $column): bool;

    /**
     * @param  array<string, mixed>  $column
     */
    public function applyOrder(Builder $query, array $column, string $direction): void;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $column
     */
    public function isFilterable(string $modelClass, array $column): bool;

    /**
     * Apply one Excel-style column filter (ColumnFilterParser syntax).
     *
     * @param  array<string, mixed>  $column
     */
    public function applyFilter(Builder $query, array $column, string $type, string $raw): void;

    /**
     * Compute the columns for the fetched rows and expose each value under the
     * column's `field`, so the list renders it like any attribute.
     *
     * @param  iterable<int, mixed>  $rows
     * @param  array<int, array<string, mixed>>  $columns
     */
    public function fillRows(iterable $rows, array $columns): void;

    /**
     * The values of computed detail fields for one record, keyed by field `name`.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public function detailValues(Model $model, array $fields): array;
}
