<?php

declare(strict_types=1);

namespace Noerd\Support\Positions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Noerd\Contracts\DefinesPositionColumns;
use Noerd\Support\SchemaColumnCache;
use Throwable;

/**
 * Merges a module's position column catalog with the `positions.columns` block
 * of a detail YAML.
 *
 * - No `positions.columns`: every catalog column with `default: true`, in
 *   catalog order (locked columns are always included).
 * - With `positions.columns`: the YAML order wins. A catalog entry may override
 *   `label` and `width` only; a locked catalog column the YAML leaves out is
 *   re-inserted after its nearest preceding present catalog column; an optional
 *   catalog column left out is hidden. A field outside the catalog is added when
 *   it is a real, non-system, non-forbidden column of the model's table.
 * - Invalid entries are dropped with a log warning — a YAML mistake never breaks
 *   the page.
 */
final class PositionColumnResolver
{
    /** Columns that never make sense as a position column. */
    public const SYSTEM_COLUMNS = ['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $pageLayout
     * @return array<int, PositionColumn>
     */
    public function resolve(DefinesPositionColumns $catalog, string $modelClass, array $pageLayout): array
    {
        /** @var array<string, PositionColumn> $catalogColumns */
        $catalogColumns = [];
        foreach ($catalog->columns($modelClass) as $column) {
            if ($column instanceof PositionColumn && $column->field !== '') {
                $catalogColumns[$column->field] ??= $column;
            }
        }

        $entries = $pageLayout['positions']['columns'] ?? null;

        if (! is_array($entries)) {
            return array_values(array_filter(
                $catalogColumns,
                fn(PositionColumn $column): bool => $column->default || $column->locked,
            ));
        }

        $forbidden = array_map('strval', $catalog->forbidden());

        /** @var array<string, PositionColumn> $resolved */
        $resolved = [];

        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $entry = ['field' => $entry];
            }

            $field = is_array($entry) && is_string($entry['field'] ?? null) ? mb_trim($entry['field']) : '';

            if ($field === '') {
                $this->warn($modelClass, 'entry without a field', $entry);

                continue;
            }

            if (isset($resolved[$field])) {
                $this->warn($modelClass, "duplicate field '{$field}'", $entry);

                continue;
            }

            if (isset($catalogColumns[$field])) {
                $resolved[$field] = $this->overrideCatalogColumn($catalogColumns[$field], $entry);

                continue;
            }

            $extra = $this->extraColumn($modelClass, $field, $entry, $forbidden);

            if ($extra !== null) {
                $resolved[$field] = $extra;
            }
        }

        return $this->reinsertLockedColumns($catalogColumns, $resolved);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function overrideCatalogColumn(PositionColumn $column, array $entry): PositionColumn
    {
        $overrides = [];

        if (is_string($entry['label'] ?? null) && $entry['label'] !== '') {
            $overrides['label'] = $entry['label'];
        }

        if (is_string($entry['width'] ?? null) && $entry['width'] !== '') {
            $overrides['width'] = $entry['width'];
        }

        return $overrides === [] ? $column : $column->with($overrides);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>  $forbidden
     */
    private function extraColumn(string $modelClass, string $field, array $entry, array $forbidden): ?PositionColumn
    {
        if (in_array($field, self::SYSTEM_COLUMNS, true)) {
            $this->warn($modelClass, "system column '{$field}' cannot be a position column", $entry);

            return null;
        }

        if (in_array($field, $forbidden, true)) {
            $this->warn($modelClass, "field '{$field}' is forbidden by the position catalog", $entry);

            return null;
        }

        if (! $this->isTableColumn($modelClass, $field)) {
            $this->warn($modelClass, "unknown field '{$field}'", $entry);

            return null;
        }

        $type = $entry['type'] ?? 'text';
        if (! in_array($type, PositionColumn::TYPES, true)) {
            $this->warn($modelClass, "unknown type for '{$field}', using text", $entry);
            $type = 'text';
        }

        return PositionColumn::fromArray([
            'field' => $field,
            'label' => is_string($entry['label'] ?? null) && $entry['label'] !== '' ? $entry['label'] : Str::headline($field),
            'width' => $entry['width'] ?? 'w-32',
            'type' => $type,
            'step' => $entry['step'] ?? null,
            'readonly' => (bool) ($entry['readonly'] ?? false),
            'options' => $type === 'select' && is_array($entry['options'] ?? null) ? $entry['options'] : [],
            'locked' => false,
            'default' => true,
            'change' => 'store',
        ]);
    }

    /**
     * Put every locked catalog column the YAML omitted back in: right after the
     * nearest preceding catalog column that is present, or at the start.
     *
     * @param  array<string, PositionColumn>  $catalogColumns
     * @param  array<string, PositionColumn>  $resolved
     * @return array<int, PositionColumn>
     */
    private function reinsertLockedColumns(array $catalogColumns, array $resolved): array
    {
        $catalogFields = array_keys($catalogColumns);

        foreach ($catalogFields as $index => $field) {
            $column = $catalogColumns[$field];

            if (! $column->locked || isset($resolved[$field])) {
                continue;
            }

            $anchor = null;
            for ($previous = $index - 1; $previous >= 0; $previous--) {
                if (isset($resolved[$catalogFields[$previous]])) {
                    $anchor = $catalogFields[$previous];
                    break;
                }
            }

            $resolved = $this->insertAfter($resolved, $anchor, $column);
        }

        return array_values($resolved);
    }

    /**
     * @param  array<string, PositionColumn>  $columns
     * @return array<string, PositionColumn>
     */
    private function insertAfter(array $columns, ?string $anchor, PositionColumn $column): array
    {
        if ($anchor === null) {
            return [$column->field => $column] + $columns;
        }

        $result = [];
        foreach ($columns as $field => $existing) {
            $result[$field] = $existing;

            if ($field === $anchor) {
                $result[$column->field] = $column;
            }
        }

        return $result;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function isTableColumn(string $modelClass, string $field): bool
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            return false;
        }

        try {
            return SchemaColumnCache::hasColumn((new $modelClass())->getTable(), $field);
        } catch (Throwable) {
            return false;
        }
    }

    private function warn(string $modelClass, string $reason, mixed $entry): void
    {
        Log::warning("Position column dropped ({$modelClass}): {$reason}", [
            'entry' => $entry,
        ]);
    }
}
