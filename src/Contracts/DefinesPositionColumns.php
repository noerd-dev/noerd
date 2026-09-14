<?php

declare(strict_types=1);

namespace Noerd\Contracts;

use Noerd\Support\Positions\PositionColumn;

/**
 * The non-removable columns of a module's position (line item) table. The
 * detail YAML (`positions.columns`) is the only source of a table's columns;
 * the code contributes ONLY the columns the module's calculation depends on
 * (quantity, prices, tax rate, totals). They are always rendered — an
 * installation may relabel, resize and move them, never remove them. Merged by
 * {@see \Noerd\Support\Positions\PositionColumnResolver}.
 */
interface DefinesPositionColumns
{
    /**
     * The non-removable columns, in the order used when the YAML omits them. The
     * position model class is passed so one catalog can serve several position
     * models.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @return array<int, PositionColumn>
     */
    public function columns(string $modelClass): array;

    /**
     * Model attributes that must never be declared as a column in the YAML —
     * typically the logic-bearing and foreign-key fields that are not in the
     * catalog (`invoice_id`, `total_tax`, …).
     *
     * @return array<int, string>
     */
    public function forbidden(): array;
}
