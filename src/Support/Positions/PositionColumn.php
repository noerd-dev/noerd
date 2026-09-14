<?php

declare(strict_types=1);

namespace Noerd\Support\Positions;

use Illuminate\Support\Str;
use JsonException;

/**
 * One column of a position (line item) table: which model attribute it binds,
 * how it is labelled and rendered, and whether an installation may change it.
 *
 * Modules declare their catalog of columns through a
 * {@see \Noerd\Contracts\DefinesPositionColumns} class; the
 * {@see PositionColumnResolver} merges it with the `positions.columns` block of
 * the detail YAML. Resolved columns travel to row components as plain arrays
 * ({@see toArray()} / {@see fromArray()}).
 *
 * Immutable: every builder method returns a new instance.
 */
final class PositionColumn
{
    public const TYPES = ['text', 'number', 'date', 'checkbox', 'select'];

    /**
     * @param  array<int, array{value: mixed, label: string}>  $options
     */
    private function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly string $width = 'w-32',
        public readonly bool $locked = false,
        public readonly bool $readonly = false,
        public readonly bool $default = true,
        public readonly string $change = 'store',
        public readonly int|float|string|null $step = null,
        public readonly array $options = [],
        public readonly string $placeholder = '',
    ) {}

    /**
     * Start a catalog column for a model attribute. The label defaults to the
     * headline of the field (`unit_price` → `Unit Price`), a translation key.
     */
    public static function make(string $field): self
    {
        return new self(field: $field, label: Str::headline($field));
    }

    /**
     * Whether a model value is an array, or a JSON string that decodes to one —
     * such values are always shown read-only as text.
     */
    public static function isArrayValue(mixed $value): bool
    {
        return is_array(self::decodeArray($value));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $step = $data['step'] ?? null;

        return new self(
            field: (string) ($data['field'] ?? ''),
            label: (string) ($data['label'] ?? Str::headline((string) ($data['field'] ?? ''))),
            type: in_array($data['type'] ?? null, self::TYPES, true) ? $data['type'] : 'text',
            width: is_string($data['width'] ?? null) && $data['width'] !== '' ? $data['width'] : 'w-32',
            locked: (bool) ($data['locked'] ?? false),
            readonly: (bool) ($data['readonly'] ?? false),
            default: (bool) ($data['default'] ?? true),
            change: is_string($data['change'] ?? null) && $data['change'] !== '' ? $data['change'] : 'store',
            step: is_int($step) || is_float($step) || is_string($step) ? $step : null,
            options: self::normalizeOptions(is_array($data['options'] ?? null) ? $data['options'] : []),
            placeholder: is_string($data['placeholder'] ?? null) ? $data['placeholder'] : '',
        );
    }

    public function label(string $label): self
    {
        return $this->with(['label' => $label]);
    }

    public function type(string $type): self
    {
        return $this->with(['type' => in_array($type, self::TYPES, true) ? $type : 'text']);
    }

    public function text(): self
    {
        return $this->type('text');
    }

    public function number(int|float|string|null $step = null): self
    {
        return $this->with(['type' => 'number', 'step' => $step ?? $this->step]);
    }

    public function date(): self
    {
        return $this->type('date');
    }

    public function checkbox(): self
    {
        return $this->type('checkbox');
    }

    /**
     * Shorthand for `options($options)`: a select column.
     *
     * @param  array<int|string, mixed>  $options
     */
    public function select(array $options = []): self
    {
        return $this->options($options);
    }

    public function width(string $width): self
    {
        return $this->with(['width' => $width]);
    }

    /**
     * The module's calculation depends on this column: it can never be removed
     * and an installation may only change its label, width and position.
     */
    public function locked(bool $locked = true): self
    {
        return $this->with(['locked' => $locked]);
    }

    public function readonly(bool $readonly = true): self
    {
        return $this->with(['readonly' => $readonly]);
    }

    /**
     * Whether an OPTIONAL column is shown while the YAML declares no
     * `positions.columns`. A locked column is always shown.
     */
    public function default(bool $default = true): self
    {
        return $this->with(['default' => $default]);
    }

    /**
     * The Livewire method `wire:change` calls on the row component.
     */
    public function onChange(string $method): self
    {
        return $this->with(['change' => $method]);
    }

    public function step(int|float|string|null $step): self
    {
        return $this->with(['step' => $step]);
    }

    /**
     * Select options, either a `value => label` map or a list of
     * `['value' => …, 'label' => …]` rows. Setting options makes the column a select.
     *
     * @param  array<int|string, mixed>  $options
     */
    public function options(array $options): self
    {
        return $this->with(['options' => self::normalizeOptions($options), 'type' => 'select']);
    }

    /**
     * The text of a select's leading empty option (default: blank).
     */
    public function placeholder(string $placeholder): self
    {
        return $this->with(['placeholder' => $placeholder]);
    }

    /**
     * Whether the row renders an input the user can change. Locked columns stay
     * editable (quantity, price) — only `readonly` switches the control off.
     */
    public function isEditable(): bool
    {
        return ! $this->readonly;
    }

    /**
     * Render a model value as text: an array (or a JSON string decoding to one)
     * becomes a comma-joined string, a scalar is cast, anything else is empty.
     */
    public function displayValue(mixed $value): string
    {
        $value = self::decodeArray($value);

        if (is_array($value)) {
            return collect($value)
                ->map(fn(mixed $item): string => match (true) {
                    is_array($item) => collect($item)
                        ->filter(fn(mixed $part): bool => is_scalar($part) && (string) $part !== '')
                        ->map(fn(mixed $part): string => (string) $part)
                        ->implode(' '),
                    is_bool($item) => $item ? __('Yes') : __('No'),
                    is_scalar($item) => (string) $item,
                    default => '',
                })
                ->filter(fn(string $item): bool => $item !== '')
                ->implode(', ');
        }

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array{field: string, label: string, type: string, width: string, locked: bool, readonly: bool, default: bool, change: string, step: int|float|string|null, options: array<int, array{value: mixed, label: string}>, placeholder: string}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'label' => $this->label,
            'type' => $this->type,
            'width' => $this->width,
            'locked' => $this->locked,
            'readonly' => $this->readonly,
            'default' => $this->default,
            'change' => $this->change,
            'step' => $this->step,
            'options' => $this->options,
            'placeholder' => $this->placeholder,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @return array<int, array{value: mixed, label: string}>
     */
    private static function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                if (! array_key_exists('value', $option)) {
                    continue;
                }

                $normalized[] = [
                    'value' => $option['value'],
                    'label' => (string) ($option['label'] ?? $option['value']),
                ];

                continue;
            }

            if (is_scalar($option)) {
                $normalized[] = ['value' => $key, 'label' => (string) $option];
            }
        }

        return $normalized;
    }

    private static function decodeArray(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = mb_ltrim($value);

        if ($trimmed === '' || ! in_array($trimmed[0], ['[', '{'], true)) {
            return $value;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }

        return is_array($decoded) ? $decoded : $value;
    }
}
