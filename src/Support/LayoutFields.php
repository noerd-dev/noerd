<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * The single "recurse into type: block" walk over a YAML field layout. Every
 * feature that derives something from the field list (writable keys,
 * validation rules, picklist options, rendered relation forms) visits the
 * fields through this walker instead of hand-rolling the recursion.
 */
final class LayoutFields
{
    /**
     * Visit every non-block field, depth-first through nested `type: block`
     * groups. The visitor may return `false` to stop the walk early.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @param  callable(array<string, mixed>): (bool|null|void)  $visitor
     * @return bool false when the visitor stopped the walk early
     */
    public static function walk(array $fields, callable $visitor): bool
    {
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (($field['type'] ?? null) === 'block') {
                if (! self::walk($field['fields'] ?? [], $visitor)) {
                    return false;
                }

                continue;
            }

            if ($visitor($field) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rewrite every non-block field, depth-first through nested `type: block`
     * groups, and return the new field list. The counterpart of walk() for
     * features that decorate the layout instead of reading from it.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @param  callable(array<string, mixed>): array<string, mixed>  $mapper
     * @return array<int, array<string, mixed>>
     */
    public static function map(array $fields, callable $mapper): array
    {
        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            if (($field['type'] ?? null) === 'block') {
                $fields[$index]['fields'] = self::map($field['fields'] ?? [], $mapper);

                continue;
            }

            $fields[$index] = $mapper($field);
        }

        return $fields;
    }

    /**
     * The tabs (ascending, unique) that hold the named fields — what a failed
     * validation needs to lead the reader to its errors. A top-level field sits
     * on its `tab:` (tab 1 without one) and a field inside a `type: block` on
     * the tab of its outermost block, the rule the tab panels are filled by. A
     * name matches its field exactly or as a path below it
     * (`detailData.positions.0.amount` → `detailData.positions`); a name the
     * layout does not declare belongs to no tab.
     *
     * @param  array<int, mixed>  $fields
     * @param  array<int, string>  $names
     * @return list<int>
     */
    public static function tabsOf(array $fields, array $names): array
    {
        $tabs = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $tab = (int) ($field['tab'] ?? 1);

            self::walk([$field], function (array $leaf) use ($names, $tab, &$tabs): void {
                $fieldName = $leaf['name'] ?? null;

                if (! is_string($fieldName) || $fieldName === '') {
                    return;
                }

                foreach ($names as $name) {
                    if ($name === $fieldName || str_starts_with($name, $fieldName . '.')) {
                        $tabs[$tab] = $tab;

                        return;
                    }
                }
            });
        }

        sort($tabs);

        return $tabs;
    }

    /**
     * Whether a field name is safe to render into the form's bindings: letters
     * (umlauts included), digits, `_`, `.`, space, `/` and `-` — the characters
     * real column paths use (`detailData.Kante A oben`), and nothing that can
     * end a string or form an expression in a directive (quotes, parentheses,
     * `;`, `=`, backticks, `<`, `>`, `$`, `{`, `}`).
     */
    public static function isSafeName(mixed $name): bool
    {
        return is_string($name) && $name !== '' && preg_match('/^[\p{L}\p{N}_. \/-]+$/u', $name) === 1;
    }

    /**
     * Drop every field (blocks recursed) whose `name` is set but not a safe name
     * ({@see isSafeName()}). A layout is configuration — the YAML, or a layout
     * override a tenant stored — and field names end up in `wire:model`,
     * `wire:click` (`type: button`) and Alpine expressions; one crafted name
     * must not become script. A field without a name (spacer, block) stays.
     *
     * @param  array<int, mixed>  $fields
     * @param  (callable(string): void)|null  $onDropped  told the name of every dropped field
     * @return array<int, mixed>
     */
    public static function withoutUnsafeNames(array $fields, ?callable $onDropped = null): array
    {
        $kept = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                $kept[] = $field;

                continue;
            }

            if (($field['type'] ?? null) === 'block') {
                $field['fields'] = self::withoutUnsafeNames(is_array($field['fields'] ?? null) ? $field['fields'] : [], $onDropped);
                $kept[] = $field;

                continue;
            }

            if (array_key_exists('name', $field) && ! self::isSafeName($field['name'])) {
                if ($onDropped !== null) {
                    $onDropped(is_scalar($field['name']) ? (string) $field['name'] : get_debug_type($field['name']));
                }

                continue;
            }

            $kept[] = $field;
        }

        return $kept;
    }
}
