<?php

declare(strict_types=1);

namespace Noerd\Services;

use Noerd\Helpers\StaticConfigHelper;
use Noerd\Support\ShellState;

/**
 * Splits the current app's navigation structure into the pieces the layout
 * views consume. Registered as a singleton — the layout injects it from
 * several views per page, and the structure itself is memoized per request
 * in StaticConfigHelper.
 */
final class NavigationService
{
    private array $blockMenus = [];

    public function __construct()
    {
        $navigationStructure = StaticConfigHelper::getNavigationStructure();

        if (! $navigationStructure) {
            return;
        }

        $app = $navigationStructure[0] ?? null;

        if (! is_array($app)) {
            return;
        }

        $blockMenu = [];
        foreach ($app['blockMenus'] ?? [] as $menu) {
            $menu['show'] = ShellState::blockMenuVisible((string) ($menu['title'] ?? ''));
            $blockMenu[] = $menu;
        }

        $this->blockMenus = $blockMenu;
    }


    public function blockMenus(): array
    {
        return $this->blockMenus;
    }
}
