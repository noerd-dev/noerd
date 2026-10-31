<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Js;

/**
 * The `showIf` / `showIfNot` conditions of a layout entry — a field, a tab, a
 * detail action — in both of their forms: the Alpine `x-show` expression the
 * browser evaluates against the Livewire component, and the server-side
 * mirror that decides whether a hidden field is validated. String form: the
 * component property is truthy (in the JavaScript sense); object form: the
 * property equals the value, compared as strings, so a select's "1" and an
 * integer column's 1 agree. Both keys on one entry combine with AND. `show:
 * false` and an unknown `viewExists` skip the entry on the server altogether.
 */
final class FieldVisibility
{
    /**
     * The `x-show` expression of an entry's conditions, or null without one.
     * The wire path is reduced to a bare property expression and the value
     * JavaScript-encoded, so a YAML value can never break out of the attribute.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function showExpression(array $entry): ?string
    {
        $expressions = [];

        foreach (['showIf' => '', 'showIfNot' => '!'] as $key => $negation) {
            $condition = $entry[$key] ?? null;

            if (is_string($condition) && $condition !== '') {
                $expressions[] = $negation . '$wire.' . self::path($condition);
            } elseif (is_array($condition) && isset($condition['field'])) {
                $expressions[] = $negation . '(String($wire.' . self::path((string) $condition['field']) . " ?? '') === "
                    . Js::from((string) ($condition['value'] ?? '')) . ')';
            }
        }

        return $expressions === [] ? null : implode(' && ', $expressions);
    }

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

        if (isset($field['showIf']) && ! self::conditionHolds($field['showIf'], $component)) {
            return false;
        }

        return ! (isset($field['showIfNot']) && self::conditionHolds($field['showIfNot'], $component));
    }

    private static function conditionHolds(mixed $condition, object $component): bool
    {
        if (is_string($condition)) {
            return self::jsTruthy(data_get($component, self::path($condition)));
        }

        if (is_array($condition) && isset($condition['field'])) {
            $actual = data_get($component, self::path((string) $condition['field']));

            return self::jsString($actual) === (string) ($condition['value'] ?? '');
        }

        return true;
    }

    /** The same sanitisation the browser expression applies to the wire path. */
    private static function path(string $path): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_.]/', '', $path);
    }

    /** JavaScript truthiness: '0' and an empty array are truthy there. */
    private static function jsTruthy(mixed $value): bool
    {
        return ! ($value === null || $value === false || $value === 0 || $value === 0.0 || $value === '');
    }

    /** `String(value ?? '')` of JavaScript: null reads as '', a boolean as true/false. */
    private static function jsString(mixed $value): ?string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => null,
        };
    }
}
