<?php

declare(strict_types=1);

namespace Noerd\Contracts;

use Noerd\Support\Positions\PositionColumn;

/**
 * The catalog of a module's position (line item) table. The core knows no
 * business module: everything it knows about a position table's columns comes
 * in through this contract, merged with the `positions.columns` block of the
 * detail YAML by {@see \Noerd\Support\Positions\PositionColumnResolver}.
 *
 * Mark every column the module's calculation depends on (quantity, prices, tax
 * rate, totals) `locked()`: an installation can then only relabel, resize and
 * reorder it, never remove it.
 */
interface DefinesPositionColumns
{
    /**
     * The catalog, in its default order. The position model class is passed so
     * one catalog can serve several position models (and omit a column one of
     * their tables lacks).
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return array<int, PositionColumn>
     */
    public function columns(string $modelClass): array;

    /**
     * Model attributes that must never be added as an extra column through the
     * YAML — typically the logic-bearing and foreign-key fields that are not in
     * the catalog (`invoice_id`, `total_tax`, …).
     *
     * @return array<int, string>
     */
    public function forbidden(): array;
}
