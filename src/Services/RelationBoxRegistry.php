<?php

namespace Noerd\Services;

use Noerd\Support\SortedRegistrations;

/**
 * Relation-box tiles contributed for a host model by optional modules, which
 * register themselves from their service provider's boot(). The host page's
 * YAML `relations:` entries render first; registry tiles append after them,
 * ordered by sort ascending (5 renders before 10, equal sorts keep
 * registration order). The host model stays agnostic of whoever plugs in —
 * a registration ceases to exist with its module (same reasoning as the
 * DetailSlotsRegistry).
 *
 * A tile mirrors the YAML relation shape (`label`, `heroicon`, `component`
 * and/or `route`, `arguments` with the '$modelId' token) plus keys YAML
 * cannot express:
 * - count:   Closure(Model): int  — for counts the host model has no relation
 *            method for (e.g. accounting counting a party's invoices)
 * - visible: Closure(Model): bool — e.g. hide the tile when the tenant lacks
 *            the contributing app
 * Closures are resolved inside the relation box at render time and never
 * become Livewire state.
 */
class RelationBoxRegistry
{
    /** @var array<class-string, array<int, array{tile: array<string, mixed>, sort: int}>> */
    private array $tiles = [];

    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $tile
     */
    public function register(string $modelClass, array $tile, int $sort = 100): void
    {
        $this->tiles[$modelClass][] = ['tile' => $tile, 'sort' => $sort];
    }

    /**
     * Tiles for the given model class, including tiles registered for a
     * parent class — a project-level subclass inherits its base's tiles.
     *
     * @param  class-string  $modelClass
     * @return array<int, array<string, mixed>>
     */
    public function for(string $modelClass): array
    {
        $entries = [];

        foreach ($this->tiles as $registeredClass => $classEntries) {
            if ($modelClass === $registeredClass || is_a($modelClass, $registeredClass, true)) {
                $entries = [...$entries, ...$classEntries];
            }
        }

        return SortedRegistrations::payloads($entries, 'tile');
    }
}
