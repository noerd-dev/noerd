<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\ComponentHook;
use Noerd\Traits\NoerdPage;

/**
 * Binds an embedded detail to the page that renders it. The store roundtrip
 * events (`storeDetail-{name}`, `detailStored-{name}`, `detailDataUpdated-{name}`)
 * are named after the detail component and reach EVERY listener of that name —
 * an embedded detail and a second instance stacked in a modal would otherwise
 * save on each other's command and adopt each other's record. The events carry
 * the host's Livewire id, and both sides ignore an event of another host.
 */
final class EmbeddedDetailHostHook extends ComponentHook
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function mount($params, $parent = null, $attributes = null): void
    {
        if (! is_object($parent) || ! self::usesNoerdPage($this->component) || ! self::usesNoerdPage($parent)) {
            return;
        }

        if (! ($params['embedded'] ?? $this->component->embedded ?? false)) {
            return;
        }

        $this->component->hostId = $parent->getId();
    }

    private static function usesNoerdPage(object $component): bool
    {
        return in_array(NoerdPage::class, class_uses_recursive($component), true);
    }
}
