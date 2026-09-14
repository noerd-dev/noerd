<?php

declare(strict_types=1);

namespace Noerd\Support\Positions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Noerd\Contracts\DefinesPositionColumns;
use Noerd\Services\PicklistRegistry;
use Noerd\Support\SchemaColumnCache;
use Throwable;

/**
 * Resolves the columns of a position table from the `positions.columns` block of
 * a detail YAML. The YAML is the only source of the table's columns — the code
 * only contributes the columns a module's calculation depends on.
 *
 * - The catalog columns ({@see DefinesPositionColumns::columns()}) can never be
 *   removed. Without `positions.columns` exactly these render, in catalog order.
 *   A YAML entry may override their `label` and `width`; one the YAML leaves out
 *   is re-inserted after its nearest preceding present catalog column.
 * - Every other column is declared in the YAML: a real, non-system,
 *   non-forbidden column of the position model's table.
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
                $catalogColumns[$column->field] ??= $column->with(['locked' => true]);
            }
        }

        $entries = $pageLayout['positions']['columns'] ?? null;

        if (! is_array($entries)) {
            return array_values($catalogColumns);
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

            $column = $this->yamlColumn($modelClass, $field, $entry, $forbidden);

            if ($column !== null) {
                $resolved[$field] = $column;
            }
        }

        return $this->reinsertCatalogColumns($catalogColumns, $resolved);
    }

    /**
     * The columns of the position table an installation may declare in the YAML:
     * every real column that is neither a catalog, system nor forbidden column,
     * as a ready YAML entry with a type derived from the schema. Layout tooling
     * offers exactly these.
     *
     * @param  class-string<Model>  $modelClass
     * @return array<int, array{field: string, label: string, type: string, readonly?: bool}>
     */
    public function addableColumns(DefinesPositionColumns $catalog, string $modelClass): array
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        $model = new $modelClass();

        try {
            $columns = SchemaColumnCache::columns($model->getTable());
        } catch (Throwable) {
            return [];
        }

        $catalogFields = array_map(
            fn(PositionColumn $column): string => $column->field,
            array_filter($catalog->columns($modelClass), fn(mixed $column): bool => $column instanceof PositionColumn),
        );
        $excluded = [...self::SYSTEM_COLUMNS, ...array_map('strval', $catalog->forbidden()), ...$catalogFields];

        $addable = [];
        foreach ($columns as $name => $column) {
            $name = (string) $name;

            if (in_array($name, $excluded, true)) {
                continue;
            }

            $addable[] = ['field' => $name, 'label' => Str::headline($name)] + $this->schemaType($model, $name, $column);
        }

        return $addable;
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
     * A column declared entirely in the YAML. Keys: `label`, `width`, `type`
     * (text|number|date|checkbox|select), `options` or `optionsMethod` (a
     * PicklistRegistry provider returning `value => label`), `placeholder`,
     * `step`, `readonly`. Its change handler is always `store`.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>  $forbidden
     */
    private function yamlColumn(string $modelClass, string $field, array $entry, array $forbidden): ?PositionColumn
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
            'options' => $type === 'select' ? $this->options($modelClass, $entry) : [],
            'placeholder' => is_string($entry['placeholder'] ?? null) ? $entry['placeholder'] : '',
            'locked' => false,
            'change' => 'store',
        ]);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<int|string, mixed>
     */
    private function options(string $modelClass, array $entry): array
    {
        if (is_array($entry['options'] ?? null)) {
            return $entry['options'];
        }

        $method = $entry['optionsMethod'] ?? null;

        if (! is_string($method) || $method === '') {
            return [];
        }

        $provider = app(PicklistRegistry::class)->resolve($method);

        if ($provider === null) {
            $this->warn($modelClass, "unknown optionsMethod '{$method}'", $entry);

            return [];
        }

        $options = $provider();

        return is_array($options) ? $options : [];
    }

    /**
     * Put every catalog column the YAML omitted back in: right after the nearest
     * preceding catalog column that is present, or at the start.
     *
     * @param  array<string, PositionColumn>  $catalogColumns
     * @param  array<string, PositionColumn>  $resolved
     * @return array<int, PositionColumn>
     */
    private function reinsertCatalogColumns(array $catalogColumns, array $resolved): array
    {
        $catalogFields = array_keys($catalogColumns);

        foreach ($catalogFields as $index => $field) {
            if (isset($resolved[$field])) {
                continue;
            }

            $anchor = null;
            for ($previous = $index - 1; $previous >= 0; $previous--) {
                if (isset($resolved[$catalogFields[$previous]])) {
                    $anchor = $catalogFields[$previous];
                    break;
                }
            }

            $resolved = $this->insertAfter($resolved, $anchor, $catalogColumns[$field]);
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

    /**
     * @param  array<string, mixed>  $column
     * @return array{type: string, readonly?: bool}
     */
    private function schemaType(Model $model, string $name, array $column): array
    {
        $typeName = mb_strtolower((string) ($column['type_name'] ?? ''));
        $type = mb_strtolower((string) ($column['type'] ?? ''));

        return match (true) {
            // Arrays/JSON and timestamps are shown, never edited, in a position row.
            $model->hasCast($name, ['array', 'json', 'object', 'collection']) || in_array($typeName, ['json', 'jsonb'], true) => ['type' => 'text', 'readonly' => true],
            in_array($typeName, ['datetime', 'timestamp', 'timestamptz', 'time'], true) => ['type' => 'text', 'readonly' => true],
            $typeName === 'date' => ['type' => 'date'],
            in_array($typeName, ['boolean', 'bool'], true) || str_starts_with($type, 'tinyint(1)') => ['type' => 'checkbox'],
            in_array($typeName, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'numeric', 'float', 'double', 'real'], true) => ['type' => 'number'],
            default => ['type' => 'text'],
        };
    }

    private function warn(string $modelClass, string $reason, mixed $entry): void
    {
        Log::warning("Position column dropped ({$modelClass}): {$reason}", [
            'entry' => $entry,
        ]);
    }
}
