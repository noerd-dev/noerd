<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Noerd\Traits\NoerdPage;

/**
 * Binds an embedded detail to the page that renders it. The store roundtrip
 * events (`storeDetail-{name}`, `detailStored-{name}`, `detailDataUpdated-{name}`)
 * are named after the detail component and reach EVERY listener of that name —
 * an embedded detail and a second instance stacked in a modal would otherwise
 * save on each other's command and adopt each other's record. The events carry
 * the host's Livewire id, and both sides ignore an event of another host.
 */
final class EmbeddedDetailHostHook extends NoerdComponentHook
{
    protected const TRAITS = [NoerdPage::class];

    /**
     * @param  array<string, mixed>  $params
     */
    public function mount($params, $parent = null, $attributes = null): void
    {
        if (! is_object($parent) || ! in_array(NoerdPage::class, self::componentTraits($parent), true)) {
            return;
        }

        if (! ($params['embedded'] ?? $this->component->embedded ?? false)) {
            return;
        }

        $this->component->hostId = $parent->getId();
    }
}
