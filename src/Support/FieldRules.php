<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Validation\Rule;
use Noerd\Services\ThemeRegistry;

/**
 * The validation rules of one layout field: `required: true` adds `required`,
 * everything else is `nullable`, and the field type adds what its input can
 * only produce when used as intended — an email, a number, a date, one of the
 * select's options, a string no longer than its varchar column. A `validation:`
 * list in the YAML replaces the type rules (Laravel rule strings).
 *
 * Readonly fields and fields of a text-only theme get no type rules: the user
 * cannot change their value, so a legacy value must not block the save.
 */
final class FieldRules
{
    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, array<string, mixed>>  $columns  schema columns of the model table, keyed by name
     * @param  mixed  $storedValue  the value the record currently holds (a select may keep it)
     * @return array<int, mixed>
     */
    public static function for(array $field, array $columns = [], mixed $storedValue = null, ?string $inheritedTheme = null): array
    {
        $required = (bool) ($field['required'] ?? false);
        $rules = [$required ? 'required' : 'nullable'];

        if (isset($field['validation'])) {
            foreach ((array) $field['validation'] as $rule) {
                if (is_string($rule) && $rule !== '' && ! in_array($rule, ['required', 'nullable'], true)) {
                    $rules[] = $rule;
                }
            }

            return $rules;
        }

        if (! empty($field['readonly']) || self::rendersAsText($field, $inheritedTheme)) {
            return $required ? ['required'] : [];
        }

        return [...$rules, ...self::typeRules($field, $columns, $storedValue)];
    }

    /**
     * Whether the field renders as text (the `display` theme), on the field or
     * inherited from its block/layout.
     *
     * @param  array<string, mixed>  $field
     */
    private static function rendersAsText(array $field, ?string $inheritedTheme): bool
    {
        $theme = $field['theme'] ?? $inheritedTheme;

        return is_string($theme) && app(ThemeRegistry::class)->get($theme)->textOnly;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, array<string, mixed>>  $columns
     * @return array<int, mixed>
     */
    private static function typeRules(array $field, array $columns, mixed $storedValue): array
    {
        $type = (string) ($field['type'] ?? 'text');

        return match ($type) {
            'email' => ['email'],
            'number', 'currency' => ['numeric'],
            'date' => ['date'],
            'checkbox' => ['boolean'],
            'select' => self::selectRules($field, $storedValue),
            'text', 'textarea' => self::stringRules($field, $columns),
            default => [],
        };
    }

    /**
     * One of the inline options — or the value the record already holds, which
     * the select shows as an extra option. Selects fed by an optionsMethod are
     * built at runtime and stay unchecked.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private static function selectRules(array $field, mixed $storedValue): array
    {
        if (! isset($field['options']) || ! is_array($field['options']) || isset($field['optionsMethod'])) {
            return [];
        }

        $values = [];
        foreach ($field['options'] as $option) {
            $values[] = (string) (is_array($option) ? ($option['value'] ?? '') : $option);
        }

        if ($storedValue !== null && $storedValue !== '' && is_scalar($storedValue)) {
            $values[] = (string) $storedValue;
        }

        return [Rule::in(array_values(array_unique($values)))];
    }

    /**
     * A string within the length of its varchar/char column.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, array<string, mixed>>  $columns
     * @return array<int, string>
     */
    private static function stringRules(array $field, array $columns): array
    {
        $column = self::columnName($field);
        $definition = $column === null ? null : ($columns[$column] ?? null);

        if (! is_array($definition)) {
            return [];
        }

        $typeName = mb_strtolower((string) ($definition['type_name'] ?? ''));
        if (! in_array($typeName, ['varchar', 'char', 'character varying', 'character'], true)) {
            return [];
        }

        if (! preg_match('/\((\d+)\)/', (string) ($definition['type'] ?? ''), $matches)) {
            return ['string'];
        }

        return ['string', 'max:' . $matches[1]];
    }

    /**
     * The model column a field binds (`detailData.name` → `name`), or null for a
     * nested path (`detailData.custom_attributes.sap`) or another property.
     *
     * @param  array<string, mixed>  $field
     */
    private static function columnName(array $field): ?string
    {
        $name = (string) ($field['name'] ?? '');

        if (! str_starts_with($name, 'detailData.')) {
            return null;
        }

        $column = mb_substr($name, mb_strlen('detailData.'));

        return str_contains($column, '.') ? null : $column;
    }
}
