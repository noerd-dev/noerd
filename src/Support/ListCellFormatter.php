<?php

declare(strict_types=1);

namespace Noerd\Support;

use BackedEnum;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\FormatHelper;
use UnitEnum;

/**
 * The one place a list cell value is turned into display text — shared by the
 * table, the minimal (widget) list, the card grid and the CSV export, so every
 * rendering mode formats a column type the same way.
 */
final class ListCellFormatter
{
    /**
     * The raw scalar of a value (enums unwrapped to their value/name).
     */
    public static function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : ($value instanceof UnitEnum ? $value->name : $value);
    }

    /**
     * Resolve a picklist value to its option label (untranslated); falls back to
     * the raw value when no option matches.
     *
     * @param  array<int, array{value?: mixed, label?: string}>  $options
     */
    public static function badgeLabel(mixed $value, array $options): string
    {
        $value = self::scalar($value);

        foreach ($options as $option) {
            if (isset($option['value']) && (string) $option['value'] === (string) $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return (string) ($value ?? '');
    }

    /**
     * Display text of a value for a column type (`text` and unknown types pass
     * the value through).
     *
     * @param  array<string, mixed>  $column
     */
    public static function format(mixed $value, array $column): string
    {
        $value = self::scalar($value);

        return match ($column['type'] ?? 'text') {
            'currency' => is_numeric($value) ? CurrencyHelper::format((float) $value) : (string) ($value ?? ''),
            'date' => FormatHelper::date($value),
            'datetime' => FormatHelper::dateTime($value),
            'number' => is_numeric($value) ? FormatHelper::number((float) $value, 2) : (string) ($value ?? ''),
            'bool', 'boolean' => self::truthy($value) ? __('Yes') : __('No'),
            'badge' => __(self::badgeLabel($value, $column['options'] ?? [])),
            'preview' => (string) (self::preview($value)['url'] ?? ''),
            default => (string) ($value ?? ''),
        };
    }

    /**
     * The picture of a `preview` cell: a URL string, or an array carrying `url`
     * (an image) or `icon` (a heroicon name shown on a tile coloured by `class`,
     * with an optional `label` such as the file extension). The core never knows
     * where a picture comes from — the list row hands it over ready to render.
     * Null when there is nothing to show.
     *
     * @return array{url: ?string, icon: ?string, class: string, label: string}|null
     */
    public static function preview(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = ['url' => $value];
        }

        if (! is_array($value)) {
            return null;
        }

        $url = is_string($value['url'] ?? null) && $value['url'] !== '' ? $value['url'] : null;
        $icon = is_string($value['icon'] ?? null) && $value['icon'] !== '' ? $value['icon'] : null;

        if ($url === null && $icon === null) {
            return null;
        }

        return [
            'url' => $url,
            'icon' => $icon,
            'class' => (string) ($value['class'] ?? 'bg-gray-100 text-gray-500'),
            'label' => (string) ($value['label'] ?? ''),
        ];
    }

    /**
     * Boolean reading of a raw column value — a model without a boolean cast
     * hands over `1`/`"0"`, which must read like `true`/`false`.
     */
    public static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
