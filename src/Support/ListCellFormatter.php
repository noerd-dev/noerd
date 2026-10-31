<?php

declare(strict_types=1);

namespace Noerd\Support;

use BackedEnum;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\FormatHelper;
use Noerd\Services\RelationTitleResolver;
use Stringable;
use UnitEnum;

/**
 * The one place a list cell value is turned into display text — shared by the
 * table, the minimal (widget) list, the card grid, the CSV export and the
 * text-only detail theme, so every rendering mode formats a column type the
 * same way. The array-shaped cell values (`colored_text`, `badge_with_text`,
 * `preview`) are normalised here as well, so no view ever reads a raw array.
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
     * the raw value when no option matches. Options are value/label rows (a
     * list YAML, a select field) or a value => label map (a picklist).
     *
     * @param  array<int|string, array{value?: mixed, label?: string}|string>  $options
     */
    public static function badgeLabel(mixed $value, array $options): string
    {
        $value = self::scalar($value);

        foreach ($options as $key => $option) {
            $optionValue = is_array($option) ? ($option['value'] ?? null) : $key;
            $optionLabel = is_array($option) ? ($option['label'] ?? $optionValue) : $option;

            if ($optionValue !== null && (string) $optionValue === self::text($value)) {
                return self::text($optionLabel ?? $value);
            }
        }

        return self::text($value);
    }

    /**
     * Display text of a value for a column type (`text` and unknown types pass
     * the value through; an array value becomes its readable line).
     *
     * @param  array<string, mixed>  $column
     */
    public static function format(mixed $value, array $column): string
    {
        $value = self::scalar($value);

        return match ($column['type'] ?? 'text') {
            'currency' => is_numeric($value) ? CurrencyHelper::format((float) $value) : self::text($value),
            'date' => FormatHelper::date($value),
            'datetime' => FormatHelper::dateTime($value),
            'time' => FormatHelper::time($value),
            'number' => is_numeric($value) ? FormatHelper::number((float) $value, 2) : self::text($value),
            'bool', 'boolean' => self::truthy($value) ? __('Yes') : __('No'),
            'inversebool' => self::truthy($value) ? __('Yes') : '',
            'badge' => __(self::badgeLabel($value, $column['options'] ?? [])),
            'relationBadge' => self::relationTitle((string) ($column['field'] ?? ''), $value),
            'customAttribute' => RelationFieldDefinition::normalizeDisplayValue($value),
            'badge_with_text' => self::line(array_values(array_diff_key(self::badgeWithText($value), ['variant' => true]))),
            'colored_text' => self::line([mb_trim(self::coloredText($value)['prefix']), self::coloredText($value)['text']]),
            'preview' => (string) (self::preview($value)['url'] ?? ''),
            default => self::text($value),
        };
    }

    /**
     * The plain text of an untyped value: null and false read as '', an array
     * as its scalar items joined with ", " (a multi-value picklist, the
     * languages of a translatable text), an object through __toString.
     */
    public static function text(mixed $value): string
    {
        $value = self::scalar($value);

        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            $items = array_filter($value, static fn(mixed $item): bool => is_scalar($item) && $item !== '' && $item !== false);

            return implode(', ', array_map(static fn(mixed $item): string => (string) $item, $items));
        }

        if (is_object($value)) {
            return $value instanceof Stringable || method_exists($value, '__toString') ? (string) $value : '';
        }

        return (string) $value;
    }

    /**
     * The title of a foreign-key value (`relationBadge`): the title resolver of
     * the registered relation type, or the name column of the related table.
     */
    public static function relationTitle(string $field, mixed $value): string
    {
        $value = self::scalar($value);

        if ($value === null || $value === '' || is_array($value) || $field === '') {
            return '';
        }

        return (string) (app(RelationTitleResolver::class)->title($field, $value) ?? '');
    }

    /**
     * Normalised shape of a `colored_text` value — a scalar, or an array with
     * `text`, `class`, `prefix`, `prefixClass`, `icon`, `iconClass`. `styled`
     * tells the view whether the text carries the chip look (an array value).
     *
     * @return array{text: string, class: string, prefix: string, prefixClass: string, icon: ?string, iconClass: string, styled: bool}
     */
    public static function coloredText(mixed $value): array
    {
        $value = self::scalar($value);
        $styled = is_array($value);
        $value = $styled ? $value : ['text' => $value];

        return [
            'text' => self::text($value['text'] ?? null),
            'class' => (string) ($value['class'] ?? ''),
            'prefix' => self::text($value['prefix'] ?? null),
            'prefixClass' => (string) ($value['prefixClass'] ?? ''),
            'icon' => is_string($value['icon'] ?? null) && $value['icon'] !== '' ? $value['icon'] : null,
            'iconClass' => (string) ($value['iconClass'] ?? 'text-gray-500'),
            'styled' => $styled,
        ];
    }

    /**
     * Normalised shape of a `badge_with_text` value — a scalar (the text alone),
     * or an array with `badge`, `text` and the `variant` of the badge component
     * (`primary` by default, `danger`, `success`, `warning`, `neutral`).
     *
     * @return array{badge: string, variant: string, text: string}
     */
    public static function badgeWithText(mixed $value): array
    {
        $value = self::scalar($value);

        if (! is_array($value)) {
            return ['badge' => '', 'variant' => 'primary', 'text' => self::text($value)];
        }

        return [
            'badge' => self::text($value['badge'] ?? null),
            'variant' => (string) ($value['variant'] ?? 'primary'),
            'text' => self::text($value['text'] ?? null),
        ];
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

    /**
     * @param  array<int, string>  $parts
     */
    private static function line(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn(string $part): bool => $part !== ''));
    }
}
