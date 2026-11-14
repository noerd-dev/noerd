<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Noerd\Helpers\AccessHelper;
use Noerd\Support\Positions\PositionColumn;

/**
 * Row component of a YAML-configurable position table.
 *
 * The hosting detail resolves the columns once (`$this->positionColumns()`)
 * and hands them to every row; the row binds one `row.{field}` value per
 * column and renders them with `<x-noerd::positions.cells>`. The resolved
 * columns are #[Locked], so the client can never widen the set of fields
 * {@see editablePositionValues()} returns.
 *
 * The row never keeps its Eloquent model as a public property: it stores the
 * #[Locked] class and key of the position and loads the record once per
 * request ({@see loadPosition()}, also reachable as the computed
 * `$this->position`). A row whose record cannot be loaded any more (deleted,
 * another tenant) writes nothing — WriteGuardHook refuses its store/delete.
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

    /** Key of the position row's record. */
    #[Locked]
    public int|string|null $positionId = null;

    /** Model class of the position (an Eloquent model, checked when loading). */
    #[Locked]
    public ?string $positionClass = null;

    /** @var array<string, mixed> model attribute => value, the `wire:model` target */
    public array $row = [];

    /** @var array<int, array<string, mixed>> resolved PositionColumn arrays */
    #[Locked]
    public array $columns = [];

    public string $theme = 'default';

    public ?int $number = null;

    /** The record loaded in this request (never serialised to the client). */
    private ?Model $loadedPosition = null;

    private bool $positionLoaded = false;

    /**
     * Deletes the position — a write of the document it belongs to, so it
     * needs the write permission of the position model.
     */
    public function delete(): void
    {
        $position = $this->loadPosition();

        if (! $position instanceof Model || ! AccessHelper::canWriteObject($position::class)) {
            return;
        }

        $position->delete();

        $this->dispatch('positionDeleted');
    }

    /**
     * The position's record, loaded once per request (null when it is gone).
     * `$this->position` reads the same record.
     */
    protected function loadPosition(): ?Model
    {
        if ($this->positionLoaded) {
            return $this->loadedPosition;
        }

        $this->positionLoaded = true;
        $class = $this->positionClass;

        if ($class === null || $this->positionId === null || ! is_a($class, Model::class, true)) {
            return $this->loadedPosition = null;
        }

        return $this->loadedPosition = $class::query()->find($this->positionId);
    }

    /**
     * Read access as `$this->position` for row components and their views.
     */
    #[Computed]
    protected function position(): ?Model
    {
        return $this->loadPosition();
    }

    /**
     * @param  Model|int|string  $position  the saved position record, or its key together with $modelClass
     * @param  array<int, array<string, mixed>>  $columns
     * @param  string|null  $modelClass  the position model class, required when $position is a key
     *
     * @throws InvalidArgumentException when a key is passed without a model class
     */
    protected function initPositionRow(Model|int|string $position, array $columns, string $theme = 'default', ?int $number = null, ?string $modelClass = null): void
    {
        if (! $position instanceof Model) {
            if ($modelClass === null || ! is_a($modelClass, Model::class, true)) {
                throw new InvalidArgumentException('A position row mounted with a key needs the model class of the position.');
            }

            $position = $modelClass::query()->findOrFail($position);
        }

        $this->positionClass = $position::class;
        $this->positionId = $position->getKey();
        $this->loadedPosition = $position;
        $this->positionLoaded = true;
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
    protected function editablePositionValues(): array
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
    protected function positionColumnCount(): int
    {
        return count($this->columns) + 1;
    }

    /**
     * @return array<int, PositionColumn>
     */
    private function positionColumnObjects(): array
    {
        return array_map(
            fn(array $column): PositionColumn => PositionColumn::fromArray($column),
            array_filter($this->columns, 'is_array'),
        );
    }
}
