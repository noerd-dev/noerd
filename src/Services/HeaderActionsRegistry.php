<?php

namespace Noerd\Services;

/**
 * Blade partials contributed to the list and detail headers by optional modules,
 * which register themselves from their service provider's boot(). Each partial is
 * @include'd with a `component` (the Livewire alias, e.g. `customer::customers-list`)
 * and a `viewType` (`list`|`detail`) context and decides for itself whether it
 * renders anything.
 *
 * Plain views rather than Livewire components on purpose: the headers re-render on
 * every Livewire update of their component (e.g. each search keystroke), and an
 * include is re-evaluated with them — no nested component lifecycle, no keys, no
 * per-list component overhead. Registration-based rather than config-based for the
 * same reason as the TopBarRegistry: a registration ceases to exist with its module.
 */
class HeaderActionsRegistry
{
    /** @var array<int, string> */
    private array $views = [];

    public function register(string $view): void
    {
        $this->views[] = $view;
    }

    /** @return array<int, string> */
    public function all(): array
    {
        return $this->views;
    }
}
