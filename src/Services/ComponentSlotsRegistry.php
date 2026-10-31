<?php

declare(strict_types=1);

namespace Noerd\Services;

use Noerd\Support\SortedRegistrations;

/**
 * Livewire components a module mounts at a named spot of the noerd chrome: the
 * top bar (TOP_BAR), the list header (LIST_HEADER_ACTIONS), the head row of a
 * detail's first form block (DETAIL_HEADER_ACTIONS) and the named extension
 * slots a detail marks with `<x-noerd::detail-slot name="…">`. The host only
 * marks the position; a module registers its component in its provider's
 * boot(), lower `sort` first, equal sorts in registration order. The registry
 * starts empty: the core contributes nothing of its own.
 */
final class ComponentSlotsRegistry
{
    public const TOP_BAR = 'top-bar';

    public const LIST_HEADER_ACTIONS = 'list-header-actions';

    public const DETAIL_HEADER_ACTIONS = 'detail-header-actions';

    /** @var array<string, list<array{component: string, sort: int}>> */
    private array $slots = [];

    public function register(string $slot, string $component, int $sort = 100): void
    {
        $this->slots[$slot][] = ['component' => $component, 'sort' => $sort];
    }

    public function has(string $slot): bool
    {
        return ($this->slots[$slot] ?? []) !== [];
    }

    /**
     * The component names registered for a slot, in render order.
     *
     * @return list<string>
     */
    public function get(string $slot): array
    {
        return SortedRegistrations::payloads($this->slots[$slot] ?? [], 'component');
    }

    /**
     * Every slot with its components in render order.
     *
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return array_map(
            fn(array $entries): array => SortedRegistrations::payloads($entries, 'component'),
            $this->slots,
        );
    }
}
