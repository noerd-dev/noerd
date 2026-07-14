<?php

namespace Noerd\Services;

/**
 * Livewire components contributed to the top bar's right-hand slot by optional
 * modules, which register themselves from their service provider's boot().
 *
 * Registration-based rather than config-based on purpose: an entry in a YAML file
 * outlives the module that wrote it and would have to be guarded against, whereas
 * a registration simply ceases to exist once the module is gone. Each component
 * decides for itself whether it renders anything.
 */
class TopBarRegistry
{
    /** @var array<int, string> */
    private array $components = [];

    public function register(string $component): void
    {
        $this->components[] = $component;
    }

    /** @return array<int, string> */
    public function all(): array
    {
        return $this->components;
    }
}
