<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Str;
use Noerd\Services\ComputedColumnRegistry;

/**
 * The single home of "is this a computed column/field" and of the way a
 * detail renders one. A computed field never holds input: its value lives in
 * the component's `$computedValues`, not in `$detailData`, so no store path —
 * the trait's or a hand-written one — can ever write it into the model.
 */
final class ComputedFields
{
    /** The component property a detail keeps its computed values in. */
    public const VALUES_PROPERTY = 'computedValues';

    /**
     * @param  array<string, mixed>  $item  a list column or a detail field
     */
    public static function isComputed(array $item): bool
    {
        return app(ComputedColumnRegistry::class)->isComputed($item);
    }

    /**
     * The computed columns of a list config, in column order.
     *
     * @param  array<int, mixed>  $columns
     * @return array<int, array<string, mixed>>
     */
    public static function columns(array $columns): array
    {
        return array_values(array_filter(
            $columns,
            fn($column): bool => is_array($column) && is_string($column['field'] ?? null) && self::isComputed($column),
        ));
    }

    /**
     * Every computed field of a detail layout (recursing into blocks).
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    public static function fields(array $fields): array
    {
        $computed = [];
        LayoutFields::walk($fields, function (array $field) use (&$computed): void {
            if (is_string($field['name'] ?? null) && self::isComputed($field)) {
                $computed[] = $field;
            }
        });

        return $computed;
    }

    /**
     * The key a computed field's value is stored under in `$computedValues`
     * (`detailData.order_count` and `order_count` both become `order_count`).
     */
    public static function valueKey(string $name): string
    {
        $key = Str::startsWith($name, 'detailData.') ? Str::after($name, 'detailData.') : $name;

        return Str::startsWith($key, self::VALUES_PROPERTY . '.') ? Str::after($key, self::VALUES_PROPERTY . '.') : $key;
    }

    /**
     * Rewrite the computed fields of a detail layout for rendering: bound to
     * `computedValues.{key}`, shown as text (display theme) and never
     * required. Idempotent, so it may run on every render.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    public static function prepareLayout(array $layout): array
    {
        if (! isset($layout['fields']) || ! is_array($layout['fields'])) {
            return $layout;
        }

        $layout['fields'] = LayoutFields::map($layout['fields'], function (array $field): array {
            if (! is_string($field['name'] ?? null) || ! self::isComputed($field)) {
                return $field;
            }

            $field['name'] = self::VALUES_PROPERTY . '.' . self::valueKey($field['name']);
            $field['theme'] = 'display';
            unset($field['required']);

            return $field;
        });

        return $layout;
    }
}
