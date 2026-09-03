<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * Render-scoped holder of the YAML field the detail block is currently rendering.
 * Set per field by `noerd::components.detail.block` and read by shared chrome such
 * as `x-noerd::input-label`, so that every element template — in every theme, plus
 * the theme fallbacks — picks up optional field keys like `helpText` without having
 * to forward them one by one. Mirrors ThemeContext.
 */
final class FieldContext
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

    /**
     * Whether the field currently being rendered stores one value per language.
     * Read by `x-noerd::input-label` so every theme marks translatable fields
     * without each element template having to forward the flag.
     */
    public static function isTranslatable(): bool
    {
        $type = self::$current['type'] ?? null;

        return is_string($type) && str_starts_with($type, 'translatable');
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
