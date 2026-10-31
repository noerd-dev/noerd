<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\View;

/**
 * Whether a layout field is shown on a component — the server-side mirror of
 * the detail block: `show: false` and an unknown `viewExists` skip the field,
 * `showIf`/`showIfNot` become the same `x-show` condition the browser evaluates
 * (string form: the property is truthy in JavaScript; object form: the property
 * strictly equals the value as a string). A hidden field is not validated.
 */
final class FieldVisibility
{
    /**
     * @param  array<string, mixed>  $field
     */
    public static function visibleOn(array $field, object $component): bool
    {
        if (array_key_exists('show', $field) && ! $field['show']) {
            return false;
        }

        if (isset($field['viewExists']) && ! View::exists((string) $field['viewExists'])) {
            return false;
        }

        // showIfNot wins, exactly like the block renders it (the later directive replaces the first).
        if (isset($field['showIfNot'])) {
            return ! self::conditionHolds($field['showIfNot'], $component);
        }

        if (isset($field['showIf'])) {
            return self::conditionHolds($field['showIf'], $component);
        }

        return true;
    }

    private static function conditionHolds(mixed $condition, object $component): bool
    {
        if (is_string($condition)) {
            return self::jsTruthy(data_get($component, self::path($condition)));
        }

        if (is_array($condition) && isset($condition['field'])) {
            $actual = data_get($component, self::path((string) $condition['field']));

            return is_string($actual) && $actual === (string) ($condition['value'] ?? '');
        }

        return true;
    }

    /** The same sanitisation the detail block applies to the wire path. */
    private static function path(string $path): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_.]/', '', $path);
    }

    /** JavaScript truthiness: '0' and an empty array are truthy there. */
    private static function jsTruthy(mixed $value): bool
    {
        return ! ($value === null || $value === false || $value === 0 || $value === 0.0 || $value === '');
    }
}
