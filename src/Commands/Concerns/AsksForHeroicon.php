<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use function Laravel\Prompts\search;

use ReflectionClass;
use WireUi\Heroicons\HeroiconsServiceProvider;

/**
 * Tenant-app icons are heroicons stored as `heroicon:outline:{name}` and
 * rendered by `noerd::app-icon`. Shared by noerd:make-app and noerd:module.
 */
trait AsksForHeroicon
{
    protected function askForHeroicon(): string
    {
        // Resolve the heroicons package through its provider, not a hard-coded vendor path.
        $iconsPath = dirname((new ReflectionClass(HeroiconsServiceProvider::class))->getFileName()) . '/views/components/outline';
        $icons = collect(scandir($iconsPath))
            ->filter(fn($file) => str_ends_with($file, '.blade.php'))
            ->map(fn($file) => str_replace('.blade.php', '', $file))
            ->values()
            ->all();

        return search(
            label: 'Search for a Heroicon',
            options: fn(string $search) => collect($icons)
                ->filter(fn($icon) => empty($search) || str_contains($icon, $search))
                ->mapWithKeys(fn($icon) => [
                    "heroicon:outline:{$icon}" => $icon,
                ])
                ->all(),
            placeholder: 'Type to search icons (e.g., "arrow", "cog", "user")...',
        );
    }

    /**
     * A bare icon name (`users`) becomes the stored form `heroicon:outline:users`;
     * an already prefixed value (`heroicon:solid:users`) is kept as is.
     */
    protected function normalizeHeroicon(string $icon): string
    {
        $icon = mb_trim($icon);

        if ($icon === '' || str_starts_with($icon, 'heroicon:')) {
            return $icon;
        }

        return "heroicon:outline:{$icon}";
    }
}
