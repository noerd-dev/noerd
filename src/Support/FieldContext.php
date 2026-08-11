<?php

namespace Noerd\Support;

/**
 * Render-scoped holder of the YAML field the detail block is currently rendering.
 * Set per field by `noerd::components.detail.block` and read by shared chrome such
 * as `x-noerd::input-label`, so that every element template — in every theme, plus
 * the theme fallbacks — picks up optional field keys like `helpText` without having
 * to forward them one by one. Mirrors ThemeContext.
 */
class FieldContext
{
    /** @var array<string, mixed>|null */
    private static ?array $current = null;

    /**
     * @param  array<string, mixed>|null  $field
     */
    public static function set(?array $field): void
    {
        self::$current = $field;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function current(): ?array
    {
        return self::$current;
    }

    public static function helpText(): ?string
    {
        $helpText = self::$current['helpText'] ?? null;

        return is_string($helpText) && mb_trim($helpText) !== '' ? $helpText : null;
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
