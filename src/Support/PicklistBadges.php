<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Str;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;

/**
 * List columns that mirror a detail picklist render as translated badges: the
 * option labels come from the PAIRED detail YAML (`{x}-list` → `{x}-detail`),
 * so a list needs no configuration of its own.
 */
final class PicklistBadges
{
    /**
     * Map of `field => options` for every `type: select` field with inline
     * options in a detail YAML, plus every value-storing collection select
     * (`valueField`), whose labels come from the tenant's collection entries.
     * Id-storing collection selects stay out — their columns are FK ids, not
     * picklist values. Memoized per detail and tenant for the request.
     *
     * @return array<string, array<int, array{value: mixed, label: string}>>
     */
    public static function optionsFromDetail(string $detailComponent): array
    {
        $key = TenantHelper::getSelectedTenantId() . '|' . $detailComponent;

        $memo = &RequestState::bucket('noerd.picklist-badges');

        return $memo[$key] ??= self::resolveOptions($detailComponent);
    }

    /**
     * Turn every column with picklist options into a badge column. A column
     * that declares its own options, a type other than text or a computed
     * value is left untouched, so a list can still opt in by hand with
     * `type: badge` + `options`.
     *
     * @param  array<string, mixed>  $listSettings
     * @param  array<string, array<int, array{value: mixed, label: string}>>  $optionsByField
     * @return array<string, mixed>
     */
    public static function apply(array $listSettings, array $optionsByField): array
    {
        if ($optionsByField === []) {
            return $listSettings;
        }

        foreach ($listSettings['columns'] ?? [] as $i => $column) {
            $field = $column['field'] ?? null;
            $type = $column['type'] ?? null;

            if ($field === null
                || ! isset($optionsByField[$field])
                || isset($column['options'])
                || ($type !== null && $type !== 'text')
                || ComputedFields::isComputed($column)) {
                continue;
            }

            $listSettings['columns'][$i]['type'] = 'badge';
            $listSettings['columns'][$i]['options'] = $optionsByField[$field];
        }

        return $listSettings;
    }

    public static function clear(): void
    {
        RequestState::forget('noerd.picklist-badges');
    }

    /**
     * @return array<string, array<int, array{value: mixed, label: string}>>
     */
    private static function resolveOptions(string $detailComponent): array
    {
        $map = [];

        LayoutFields::walk(StaticConfigHelper::tryGetComponentFields($detailComponent)['fields'] ?? [], function (array $field) use (&$map): void {
            if (! isset($field['name'])) {
                return;
            }

            $key = Str::after($field['name'], 'detailData.');
            $type = $field['type'] ?? null;

            if ($type === 'setupCollectionSelect' && ! empty($field['collectionKey']) && ! empty($field['valueField'])) {
                $options = SetupCollectionHelper::selectOptions($field['collectionKey'], $field['displayField'] ?? 'name', $field['valueField']);
                if ($options !== []) {
                    $map[$key] = $options;
                }

                return;
            }

            if ($type === 'select' && ! empty($field['options'])) {
                $map[$key] = $field['options'];
            }
        });

        return $map;
    }
}
