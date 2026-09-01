<?php

namespace Noerd\Services;

use Noerd\Support\SortedRegistrations;

/**
 * Livewire components contributed into named detail slots by optional modules,
 * which register themselves from their service provider's boot(). A hosting
 * detail marks the position with `<x-noerd::detail-slot name="…">`; the core
 * ships the slot but stays agnostic of whoever fills it — a registration
 * ceases to exist with its module (same reasoning as the HeaderActionsRegistry).
 *
 * Entries are Livewire component names (e.g. `mymodule::user-extras`) with a
 * sort value: lower sorts render first (5 renders above 10), equal sorts keep
 * registration order. Each component is mounted with the host's `modelId` and
 * `hostComponent` (the host's Livewire alias) — the latter lets a slot child
 * listen for the host's `detailStored-{hostComponent}` event to defer its own
 * persistence until the host actually saved.
 */
class DetailSlotsRegistry
{
    /** @var array<string, array<int, array{component: string, sort: int}>> */
    private array $slots = [];

    public function register(string $slot, string $component, int $sort = 100): void
    {
        $this->slots[$slot][] = ['component' => $component, 'sort' => $sort];
    }

    /** @return array<int, string> */
    public function for(string $slot): array
    {
        return SortedRegistrations::payloads($this->slots[$slot] ?? [], 'component');
    }
}
