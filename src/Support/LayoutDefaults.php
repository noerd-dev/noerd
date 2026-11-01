<?php

declare(strict_types=1);

namespace Noerd\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Resolves the initial values a YAML field layout declares for the form bucket.
 *
 * A detail form must never display a value it does not hold: a `<select>` whose
 * bound property is null shows its first option purely by HTML fallback, and
 * that phantom value is lost on save. The layout therefore owns the defaults —
 * an explicit `default:` on any field, or, for a select whose options are
 * written in the YAML, the first option.
 *
 * @internal
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
            if (! is_string($name) || ! str_starts_with($name, 'detailData.')) {
                return;
            }

            $key = mb_substr($name, mb_strlen('detailData.'));
            if ($key === '') {
                return;
            }

            if (array_key_exists('default', $field)) {
                $defaults[$key] = self::resolveToken($field['default']);

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
     * A date default relative to the day the form opens: `today`, `now`, or a
     * signed offset such as `+14 days`, `-1 month`, `+2 weeks`, `+1 year`
     * (`today` as the base). `today` and the offsets resolve to a date
     * (`Y-m-d`), `now` to a date-time (`Y-m-d H:i:s`). Any other value is
     * returned as written.
     */
    public static function resolveToken(mixed $default): mixed
    {
        if (! is_string($default)) {
            return $default;
        }

        $token = mb_trim($default);

        if ($token === 'today') {
            return Carbon::today()->toDateString();
        }

        if ($token === 'now') {
            return Carbon::now()->format('Y-m-d H:i:s');
        }

        if (preg_match('/^([+-])\s*(\d+)\s*(day|days|week|weeks|month|months|year|years)$/i', $token, $matches) !== 1) {
            return $default;
        }

        $amount = (int) $matches[2] * ($matches[1] === '-' ? -1 : 1);
        $unit = Str::plural(mb_strtolower($matches[3]));

        return Carbon::today()->add($unit, $amount)->toDateString();
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
        if (! is_array($options) || $options === []) {
            return null;
        }

        $first = reset($options);

        // Options come in two shapes: `- value: x / label: X` and the simple
        // `- 'X'` form, where the label doubles as the value.
        $value = is_array($first) ? ($first['value'] ?? null) : $first;

        return is_string($value) || is_int($value) || is_float($value) ? $value : null;
    }
}
