<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Http\Request;

/**
 * The rules of a sub-navigation: a block of `blockMenus` marked `submenu: true`
 * shows as ONE entry in the sidebar; clicking it replaces the sidebar with the
 * block's entries and a "Back" link. The page the reader is on decides which
 * sub-navigation is open when the sidebar renders.
 */
final class NavigationSubmenus
{
    /**
     * Whether a block renders as a sub-navigation. A block whose entries were all
     * filtered out (config, superAdmin, unregistered routes) renders nothing.
     *
     * @param  array<string, mixed>  $block
     */
    public static function isSubmenu(array $block): bool
    {
        return ($block['submenu'] ?? false) === true
            && is_array($block['navigations'] ?? null)
            && $block['navigations'] !== [];
    }

    /**
     * The key of the first sub-navigation holding the page of the request, or
     * null when the page belongs to none.
     *
     * @param  array<int|string, array<string, mixed>>  $blocks
     */
    public static function activeIndex(array $blocks, Request $request): int|string|null
    {
        foreach ($blocks as $key => $block) {
            if (! is_array($block) || ! self::isSubmenu($block)) {
                continue;
            }

            foreach ($block['navigations'] as $navi) {
                if (is_array($navi) && self::isActiveEntry($navi, $request)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Whether a navigation entry is the page of the request: a `link:` entry by
     * its path, every other entry by its `route:` name.
     *
     * @param  array<string, mixed>  $navi
     */
    public static function isActiveEntry(array $navi, Request $request): bool
    {
        if (isset($navi['link']) && is_string($navi['link'])) {
            return $request->is(mb_ltrim($navi['link'], '/'));
        }

        $route = $navi['route'] ?? null;

        return is_string($route) && $route !== '' && $request->routeIs($route);
    }
}
