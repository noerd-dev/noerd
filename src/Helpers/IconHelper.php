<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use ReflectionClass;
use WireUi\Heroicons\HeroiconsServiceProvider;

final class IconHelper
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

        // Resolved from the installed package itself, not base_path(): the
        // vendor directory is not inside base_path() when this module runs
        // against the testbench skeleton or a non-standard install.
        $packageSrc = dirname((new ReflectionClass(HeroiconsServiceProvider::class))->getFileName());
        $directory = $packageSrc . '/views/components/outline';

        $names = array_map(
            static fn(string $file): string => basename($file, '.blade.php'),
            glob($directory . '/*.blade.php') ?: [],
        );

        sort($names);

        return self::$heroicons = $names;
    }
}
