<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * Request-scoped holder of the theme the current detail/page render runs in.
 * Set by the rendering component (NoerdPage/NoerdDetail hooks) and by the
 * detail block; read by chrome components such as `x-noerd::button` that sit
 * outside the YAML field grid but should follow the active theme.
 */
class ThemeContext
{
    private static ?string $current = null;

    public static function set(?string $theme): void
    {
        self::$current = $theme;
    }

    public static function current(): ?string
    {
        return self::$current;
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
