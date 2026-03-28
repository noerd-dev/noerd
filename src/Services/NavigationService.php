<?php

namespace Noerd\Services;

use Noerd\Helpers\StaticConfigHelper;

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

        $collection = collect($navigationStructure);
        $result = $collection[0] ?? null;

        $blockMenu = [];
        foreach ($result['block_menus'] ?? [] as $menu) {
            $menu['show'] = ! session('navi_hidden_' . $menu['title']);
            if (isset($menu['if'])) {
                $methodName = $menu['if'];
                if ($this->{$methodName}()) {
                    $blockMenu[] = $menu;
                }
            } else {
                $blockMenu[] = $menu;
            }
        }

        if ($result) {
            $this->subMenu = $result['sub_menu'] ?? [];
            $this->blockMenus = $blockMenu;
        }
    }

    public function mainMenu(): array
    {
        return StaticConfigHelper::getNavigationStructure() ?? [];
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
