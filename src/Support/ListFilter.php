<?php

declare(strict_types=1);

namespace Noerd\Support;

use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Noerd\Services\PicklistRegistry;

/**
 * One header filter of a list: the dropdown the filter row renders and the
 * constraint it puts on the query. A list declares its filters in the list
 * YAML (`filters:`) or returns them from `get{Name}ListFilter()` methods;
 * `NoerdList::listQuery()` applies every active one itself.
 *
 * A filter on a REAL column of the table constrains it by itself (equality,
 * or `>=` / `<=` for the date kinds). A filter on a virtual column — a value
 * the list resolves in its own query — carries an `apply()` closure, or is
 * marked `manual()` when the component reads `$this->listFilters[...]` itself;
 * such a column is never written into the SQL by the trait.
 */
final class ListFilter
{
    public const KIND_PICKLIST = 'picklist';

    public const KIND_DATE_FROM = 'dateFrom';

    public const KIND_DATE_UNTIL = 'dateUntil';

    /** The relative periods the date kinds offer next to a picked date. */
    public const DATE_PRESETS = ['today', 'this_week', 'this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year'];

    /** The `type` the filter views branch on, per kind. */
    private const VIEW_TYPES = [
        self::KIND_PICKLIST => 'Picklist',
        self::KIND_DATE_FROM => 'ShowFrom',
        self::KIND_DATE_UNTIL => 'ShowUntil',
    ];

    /** @var array<string, string>|null */
    private ?array $resolvedOptions = null;

    private ?string $default = null;

    private ?Closure $apply = null;

    private ?string $modelClass = null;

    /**
     * @param  array<int|string, mixed>|Closure(?string $modelClass): array<int|string, mixed>  $options
     */
    private function __construct(
        public readonly string $label,
        public readonly string $column,
        public readonly string $kind,
        private readonly array|Closure $options = [],
    ) {}

    /**
     * A dropdown of fixed options: `value => label` pairs, or the detail-YAML
     * shape (a list of `['value' => …, 'label' => …]` entries). A closure builds
     * them at render time and receives the list's model class.
     *
     * @param  array<int|string, mixed>|Closure(?string $modelClass): array<int|string, mixed>  $options
     */
    public static function options(string $label, string $column, array|Closure $options): self
    {
        return new self($label, $column, self::KIND_PICKLIST, $options);
    }

    /**
     * One option per row of a model (its global scopes — the tenant — apply):
     * the id as value, `$labelColumn` as label. `$scope` narrows or orders the
     * query (`fn (Builder $q) => $q->where('is_active', true)`).
     *
     * @param  class-string<Model>  $modelClass
     * @param  Closure(Builder $query): mixed|null  $scope
     */
    public static function model(string $label, string $column, string $modelClass, string $labelColumn = 'name', ?Closure $scope = null): self
    {
        return new self($label, $column, self::KIND_PICKLIST, function () use ($modelClass, $labelColumn, $scope): array {
            $query = $modelClass::query()->orderBy($labelColumn);

            if ($scope !== null) {
                $scope($query);
            }

            return $query->pluck($labelColumn, (new $modelClass())->getKeyName())->all();
        });
    }

    /**
     * The distinct years a date column holds, newest first; a selection narrows
     * the rows to that calendar year. Portable — no `YEAR()` in the SQL of the
     * list, so the same list runs on MySQL, sqlite and PostgreSQL.
     */
    public static function years(string $label, string $column): self
    {
        $filter = new self($label, $column, self::KIND_PICKLIST, function (?string $modelClass) use ($column): array {
            if ($modelClass === null) {
                return [];
            }

            $query = $modelClass::query()->whereNotNull($column);
            $years = $query
                ->selectRaw('DISTINCT ' . self::yearExpression($query, $column) . ' as filter_year')
                ->orderByDesc('filter_year')
                ->pluck('filter_year');

            $options = [];
            foreach ($years as $year) {
                $options[(string) (int) $year] = (string) (int) $year;
            }

            return $options;
        });

        return $filter->apply(function (Builder $query, string $value) use ($column): void {
            if (! preg_match('/^\d{4}$/', $value)) {
                return;
            }

            $column = $query->qualifyColumn($column);
            $query->where($column, '>=', "{$value}-01-01")
                ->where($column, '<', ((int) $value + 1) . '-01-01');
        });
    }

    /**
     * Rows on or after a day: a dropdown of relative periods (today, this week,
     * …) plus a date picker, compared with `>=` against `$column`.
     */
    public static function dateFrom(string $label, string $column): self
    {
        return new self($label, $column, self::KIND_DATE_FROM, self::datePresetOptions());
    }

    /**
     * Rows on or before a day, compared with `<=` against `$column`.
     */
    public static function dateUntil(string $label, string $column): self
    {
        return new self($label, $column, self::KIND_DATE_UNTIL, self::datePresetOptions());
    }

    /**
     * A filter from its array form — the shape `get{Name}ListFilter()` methods
     * returned so far (`label`, `column`, `type` Picklist|ShowFrom|ShowUntil,
     * `options` as `value => label`) and the list YAML `filters:` entry (`type`
     * picklist|years|model|dateFrom|dateUntil, `options` as in a detail select,
     * `optionsMethod` naming a PicklistRegistry provider, `model` + `labelColumn`,
     * `default`). YAML labels are translation keys.
     *
     * @param  array<string, mixed>  $filter
     */
    public static function fromArray(array $filter, ?string $modelClass = null, bool $translate = false): self
    {
        $label = (string) ($filter['label'] ?? '');
        $label = $translate ? __($label) : $label;
        $column = (string) ($filter['column'] ?? '');
        $type = (string) ($filter['type'] ?? self::KIND_PICKLIST);

        $instance = match ($type) {
            'ShowFrom', self::KIND_DATE_FROM => self::dateFrom($label, $column),
            'ShowUntil', self::KIND_DATE_UNTIL => self::dateUntil($label, $column),
            'years' => self::years($label, $column),
            'model' => self::model($label, $column, (string) ($filter['model'] ?? ''), (string) ($filter['labelColumn'] ?? 'name')),
            default => self::options($label, $column, self::optionsFromArray($filter, $translate)),
        };

        if ($modelClass !== null) {
            $instance->forModel($modelClass);
        }

        if (array_key_exists('default', $filter) && $filter['default'] !== null) {
            $instance->default((string) $filter['default']);
        }

        return $instance;
    }

    /**
     * A date-filter value as a day: one of the relative presets, or a date the
     * reader picked (`2026-03-31`); null when it is neither.
     */
    public static function resolveDate(string $value): ?string
    {
        $today = Carbon::today();

        return match ($value) {
            'today' => $today->toDateString(),
            'this_week' => $today->startOfWeek()->toDateString(),
            'this_month' => $today->startOfMonth()->toDateString(),
            'last_month' => $today->subMonth()->startOfMonth()->toDateString(),
            'this_quarter' => $today->firstOfQuarter()->toDateString(),
            'last_quarter' => $today->subQuarter()->firstOfQuarter()->toDateString(),
            'this_year' => $today->startOfYear()->toDateString(),
            default => self::parseDate($value),
        };
    }

    /**
     * The value assumed while the filter has no stored selection — applied on
     * mount and persisted once the reader changes anything. Clearing all filters
     * stores the empty selection, so "all" survives a reload too.
     */
    public function default(?string $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function defaultValue(): ?string
    {
        return $this->default;
    }

    /**
     * The constraint for a virtual column: `fn (Builder $query, string $value)`.
     * Replaces the equality the trait would otherwise put on the column.
     *
     * @param  Closure(Builder $query, string $value): mixed  $apply
     */
    public function apply(Closure $apply): self
    {
        $this->apply = $apply;

        return $this;
    }

    /**
     * The component applies this filter in its own query (it reads
     * `$this->listFilters[column]`): the trait never writes the column into SQL.
     */
    public function manual(): self
    {
        $this->apply = static function (): void {};

        return $this;
    }

    /**
     * The model whose rows the options are built from (`years()`, closures).
     * NoerdList passes its `$listModel`; a filter built for another model
     * names it here.
     */
    public function forModel(?string $modelClass): self
    {
        if ($modelClass !== null && $this->modelClass !== $modelClass) {
            $this->modelClass = $modelClass;
            $this->resolvedOptions = null;
        }

        return $this;
    }

    /**
     * The dropdown entries, `value => label`, built once per request.
     *
     * @return array<string, string>
     */
    public function resolvedOptions(): array
    {
        if ($this->resolvedOptions !== null) {
            return $this->resolvedOptions;
        }

        $options = $this->options instanceof Closure ? ($this->options)($this->modelClass) : $this->options;

        return $this->resolvedOptions = self::normalizeOptions($options);
    }

    /**
     * Put the active selection on the query. A virtual column without an
     * `apply()` closure is left alone — writing it into SQL would raise a
     * database error for a column that does not exist.
     */
    public function applyTo(Builder $query, string $value): void
    {
        if ($this->apply !== null) {
            ($this->apply)($query, $value);

            return;
        }

        if (! SchemaColumnCache::hasColumn($query->getModel()->getTable(), $this->column)) {
            return;
        }

        $column = $query->qualifyColumn($this->column);

        match ($this->kind) {
            self::KIND_DATE_FROM => self::whereDate($query, $column, '>=', $value),
            self::KIND_DATE_UNTIL => self::whereDate($query, $column, '<=', $value),
            default => $query->where($column, $value),
        };
    }

    /**
     * The array the filter views render: `label`, `column`, `type`
     * (Picklist | ShowFrom | ShowUntil) and `options`.
     *
     * @return array{label: string, column: string, type: string, options: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'column' => $this->column,
            'type' => self::VIEW_TYPES[$this->kind],
            'options' => $this->resolvedOptions(),
        ];
    }

    private static function whereDate(Builder $query, string $column, string $operator, string $value): void
    {
        $date = self::resolveDate($value);

        if ($date !== null) {
            $query->where($column, $operator, $date);
        }
    }

    private static function parseDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private static function datePresetOptions(): array
    {
        return [
            'today' => __('Today'),
            'this_week' => __('This Week'),
            'this_month' => __('This Month'),
            'last_month' => __('Last Month'),
            'this_quarter' => __('This Quarter'),
            'last_quarter' => __('Last Quarter'),
            'this_year' => __('This Year'),
        ];
    }

    /**
     * The options of an array-form filter: inline `options` (either shape) or
     * a PicklistRegistry provider named by `optionsMethod`.
     *
     * @param  array<string, mixed>  $filter
     * @return array<int|string, mixed>|Closure(?string): array<int|string, mixed>
     */
    private static function optionsFromArray(array $filter, bool $translate): array|Closure
    {
        if (is_string($filter['optionsMethod'] ?? null) && $filter['optionsMethod'] !== '') {
            $provider = $filter['optionsMethod'];

            return static function () use ($provider): array {
                $options = app(PicklistRegistry::class)->get($provider);

                return $options === null ? [] : (array) $options();
            };
        }

        $options = is_array($filter['options'] ?? null) ? $filter['options'] : [];

        if (! $translate) {
            return $options;
        }

        $translated = [];
        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $option['label'] = __((string) ($option['label'] ?? $option['value'] ?? ''));
                $translated[$key] = $option;
            } else {
                $translated[$key] = __((string) $option);
            }
        }

        return $translated;
    }

    /**
     * Both option shapes become `value => label`; an empty value is dropped —
     * the dropdown's first entry (the label) already stands for "all".
     *
     * @param  array<int|string, mixed>  $options
     * @return array<string, string>
     */
    private static function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $value = (string) ($option['value'] ?? '');
                $label = (string) ($option['label'] ?? $value);
            } else {
                $value = (string) $key;
                $label = (string) $option;
            }

            if ($value === '') {
                continue;
            }

            $normalized[$value] = $label;
        }

        return $normalized;
    }

    /**
     * The SQL that extracts the year of a date column on the query's driver.
     */
    private static function yearExpression(Builder $query, string $column): string
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn($column));

        return match ($query->getModel()->getConnection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', {$wrapped}) AS INTEGER)",
            'pgsql' => "EXTRACT(YEAR FROM {$wrapped})::int",
            default => "YEAR({$wrapped})",
        };
    }
}
