<?php

namespace Noerd\Services;

/**
 * Livewire components contributed to the list and detail headers by optional
 * modules, which register themselves from their service provider's boot().
 * Entries are Livewire component names (e.g. `plus::list-header-action-layout-manager`).
 *
 * List and detail slots are separate registries: an action usable in both
 * contexts registers twice. Each action is mounted with a `model` (the host's
 * $listModel/$detailModel, null when undeclared) and a `component` (the host's
 * Livewire alias) and decides for itself whether it renders anything — a hidden
 * action renders an empty collapsing root.
 *
 * The headers re-render on every Livewire update of their component (e.g. each
 * search keystroke), but the action components are mounted with stable keys and
 * mount-only params, so subsequent parent renders emit a memo placeholder and
 * morph leaves the child DOM untouched — gating runs once per page lifecycle.
 * Registration-based rather than config-based for the same reason as the
 * TopBarRegistry: a registration ceases to exist with its module.
 */
class HeaderActionsRegistry
{
    /** @var array<int, string> */
    private array $listActions = [];

    /** @var array<int, string> */
    private array $detailActions = [];

    public function registerListAction(string $component): void
    {
        $this->listActions[] = $component;
    }

    public function registerDetailAction(string $component): void
    {
        $this->detailActions[] = $component;
    }

    /** @return array<int, string> */
    public function listActions(): array
    {
        return $this->listActions;
    }

    /** @return array<int, string> */
    public function detailActions(): array
    {
        return $this->detailActions;
    }
}
