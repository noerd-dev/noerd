<?php

namespace Nywerk\Noerd\Services;

use Nywerk\Noerd\Helpers\StaticConfigHelper;

class NavigationService
{
    private array $subMenu = [];
    private array $blockMenus = [];

    public function __construct()
    {
        $collection = collect(StaticConfigHelper::getNavigationStructure());
        // TODO: dont use from session?
        //$result = $collection->first(fn($item) => $item['name'] === session('currentApp'));
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

    private function invoiceFeatureEnabled(): bool
    {
        return (bool) env('INVOICE_FEATURE_ENABLED', true);
    }

    private function roleFeatureEnabled(): bool
    {
        return (bool) env('ROLE_FEATURE_ENABLED', true);
    }

    private function newTenantFeature(): bool
    {
        return (bool) env('NEW_TENANT_FEATURE_ENABLED', true);
    }
}
