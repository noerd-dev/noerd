<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Noerd\Support\Positions\PositionColumn;

/**
 * Row component of a YAML-configurable position table.
 *
 * The hosting detail resolves the columns once (`$this->positionColumns(...)`)
 * and hands them to every row; the row binds one `row.{field}` value per
 * column and renders them with `<x-noerd::positions.cells>`. The resolved
 * columns are #[Locked], so the client can never widen the set of fields
 * {@see editablePositionValues()} returns.
 */
trait NoerdPositionRow
{
    /** Model casts whose value is rendered read-only as a joined list. */
    private const ARRAY_CASTS = [
        'array',
        'json',
        'object',
        'collection',
        'encrypted:array',
        'encrypted:collection',
        'encrypted:json',
        'encrypted:object',
        AsArrayObject::class,
        AsCollection::class,
    ];

    public $position;

    /** @var array<string, mixed> model attribute => value, the `wire:model` target */
    public array $row = [];

    /** @var array<int, array<string, mixed>> resolved PositionColumn arrays */
    #[Locked]
    public array $columns = [];

    public string $theme = 'default';

    public ?int $number = null;

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    public function initPositionRow(Model $position, array $columns, string $theme = 'default', ?int $number = null): void
    {
        $this->position = $position;
        $this->theme = $theme;
        $this->number = $number;

        // An array/JSON cast attribute is always shown as text and never written.
        $this->columns = array_values(array_map(
            fn(array $column): array => $position->hasCast((string) ($column['field'] ?? ''), self::ARRAY_CASTS)
                ? array_merge($column, ['readonly' => true])
                : $column,
            array_filter($columns, 'is_array'),
        ));

        $this->row = [];
        foreach ($this->positionColumnObjects() as $column) {
            $value = $position->getAttribute($column->field);

            $this->row[$column->field] = match (true) {
                $value === null && $position->hasCast($column->field, self::ARRAY_CASTS) => [],
                $value instanceof CarbonInterface => $value->format('Y-m-d'),
                $column->type === 'checkbox' => (bool) $value,
                default => $value,
            };
        }
    }

    /**
     * The values of every editable, NOT locked column, ready for `fill()`.
     * Locked (calculated) and readonly fields are never part of it — the module
     * writes those itself. Array values are always readonly.
     *
     * @return array<string, mixed>
     */
    public function editablePositionValues(): array
    {
        $values = [];

        foreach ($this->positionColumnObjects() as $column) {
            if ($column->locked || ! $column->isEditable()) {
                continue;
            }

            $value = $this->row[$column->field] ?? null;

            if (PositionColumn::isArrayValue($value)) {
                continue;
            }

            $values[$column->field] = match ($column->type) {
                'date' => $value === '' ? null : $value,
                'number' => $value === '' || $value === null ? null : (is_numeric($value) ? $value + 0 : null),
                'checkbox' => (bool) $value,
                default => $value,
            };
        }

        return $values;
    }

    /**
     * Resolved columns plus the trailing action column — the `colspan` for
     * `<x-noerd::positions.row>`.
     */
    public function positionColumnCount(): int
    {
        return count($this->columns) + 1;
    }

    public function delete(): void
    {
        $this->position?->delete();

        $this->dispatch('positionDeleted');
    }

    /**
     * @return array<int, PositionColumn>
     */
    protected function positionColumnObjects(): array
    {
        return array_map(
            fn(array $column): PositionColumn => PositionColumn::fromArray($column),
            array_filter($this->columns, 'is_array'),
        );
    }
}
