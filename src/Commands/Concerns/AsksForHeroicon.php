<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use function Laravel\Prompts\search;

use Noerd\Helpers\IconHelper;

/**
 * Tenant-app icons are heroicons stored as `heroicon:outline:{name}` and
 * rendered by `noerd::app-icon`. Shared by noerd:make-app and noerd:make-module.
 */
trait AsksForHeroicon
{
    protected function askForHeroicon(): string
    {
        // The single discovery of the shipped heroicons (memoized per request).
        $icons = IconHelper::heroicons();

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
