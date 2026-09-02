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
}
