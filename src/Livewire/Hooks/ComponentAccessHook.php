<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Noerd\Support\ComponentAccessGuard;

/**
 * Enforces the admin guard for restricted components on EVERY mount and hydrate
 * and the route requirements of a component the modal stack opens,
 * regardless of how the component was reached — its own route or the modal stack. Because the check lives here (a noerd component
 * hook), the modal system needs no knowledge of noerd's authorization: it just
 * mounts a component and this hook rejects an unauthorized one. It attaches to
 * every component (no TRAITS): a restricted component need not be a noerd one.
 *
 * @internal
 */
final class ComponentAccessHook extends NoerdComponentHook
{
    /** The modal stack component of noerd/modal, under its bare and its namespaced name. */
    private const MODAL_STACK = ['noerd-modal', 'noerd-modal::noerd-modal'];

    public function boot(): void
    {
        ComponentAccessGuard::authorize($this->component->getName());
    }

    /**
     * A component the modal stack mounts was named by the browser and never
     * passed its route middleware — the access middleware of its routes is
     * re-checked here.
     *
     * @param  array<string, mixed>  $params
     */
    public function mount($params, $parent = null, $attributes = null): void
    {
        if (is_object($parent) && method_exists($parent, 'getName') && in_array($parent->getName(), self::MODAL_STACK, true)) {
            ComponentAccessGuard::authorizeModal($this->component->getName());
        }
    }
}
