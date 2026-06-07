<?php

namespace Noerd\Helpers;

class IconHelper
{
    /**
     * @var list<string>|null
     */
    private static ?array $heroicons = null;

    /**
     * All available outline heroicon names, sorted alphabetically.
     *
     * @return list<string>
     */
    public static function heroicons(): array
    {
        if (self::$heroicons !== null) {
            return self::$heroicons;
        }

        $directory = base_path('vendor/wireui/heroicons/src/views/components/outline');

        $names = array_map(
            static fn(string $file): string => basename($file, '.blade.php'),
            glob($directory . '/*.blade.php') ?: [],
        );

        sort($names);

        return self::$heroicons = $names;
    }
}
