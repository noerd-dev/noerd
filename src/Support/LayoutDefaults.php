<?php

namespace Noerd\Support;

/**
 * Resolves the initial values a YAML field layout declares for the form bucket.
 *
 * A detail form must never display a value it does not hold: a `<select>` whose
 * bound property is null shows its first option purely by HTML fallback, and
 * that phantom value is lost on save. The layout therefore owns the defaults —
 * an explicit `default:` on any field, or, for a select whose options are
 * written in the YAML, the first option.
 */
final class LayoutDefaults
{
    /**
     * Default values a layout declares, keyed by the dot path relative to the
     * `detailData.` form bucket (e.g. `invoice_status`).
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public static function resolve(array $fields): array
    {
        $defaults = [];

        LayoutFields::walk($fields, function (array $field) use (&$defaults): void {
            $name = $field['name'] ?? null;
            if (!is_string($name) || !str_starts_with($name, 'detailData.')) {
                return;
            }

            $key = mb_substr($name, mb_strlen('detailData.'));
            if ($key === '') {
                return;
            }

            if (array_key_exists('default', $field)) {
                $defaults[$key] = $field['default'];

                return;
            }

            $implicit = self::firstSelectOption($field);
            if ($implicit !== null) {
                $defaults[$key] = $implicit;
            }
        });

        return $defaults;
    }

    /**
     * The implicit default of a select: its first option. Only applies to
     * options written in the YAML — a `placeholder:` marks empty as a valid
     * state, and `optionsMethod:` builds the list from data at runtime, where
     * "the first row wins" would be arbitrary.
     *
     * @param  array<string, mixed>  $field
     */
    private static function firstSelectOption(array $field): string|int|float|null
    {
        if (($field['type'] ?? null) !== 'select') {
            return null;
        }

        if (($field['placeholder'] ?? null) || ($field['optionsMethod'] ?? null)) {
            return null;
        }

        $options = $field['options'] ?? null;
        if (!is_array($options) || $options === []) {
            return null;
        }

        $first = reset($options);

        // Options come in two shapes: `- value: x / label: X` and the simple
        // `- 'X'` form, where the label doubles as the value.
        $value = is_array($first) ? ($first['value'] ?? null) : $first;

        return is_string($value) || is_int($value) || is_float($value) ? $value : null;
    }
}
