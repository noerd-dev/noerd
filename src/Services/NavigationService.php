<?php

namespace Noerd\Services;

use Noerd\Helpers\StaticConfigHelper;

/**
 * Splits the current app's navigation structure into the pieces the layout
 * views consume. Registered as a singleton — the layout injects it from
 * several views per page, and the structure itself is memoized per request
 * in StaticConfigHelper.
 */
class NavigationService
{
    private array $subMenu = [];

    private array $blockMenus = [];

    public function __construct()
    {
        $navigationStructure = StaticConfigHelper::getNavigationStructure();

        if (! $navigationStructure) {
            return;
        }

        $result = collect($navigationStructure)[0] ?? null;

        $blockMenu = [];
        foreach ($result['block_menus'] ?? [] as $menu) {
            $menu['show'] = ! session('navi_hidden_' . $menu['title']);
            $blockMenu[] = $menu;
        }

        if ($result) {
            $this->subMenu = $result['sub_menu'] ?? [];
            $this->blockMenus = $blockMenu;
        }
    }

    public function subMenu(): array
    {
        return $this->subMenu;
    }

    public function blockMenus(): array
    {
        return $this->blockMenus;
    }
}
