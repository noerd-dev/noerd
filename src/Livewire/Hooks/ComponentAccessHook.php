<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Noerd\Support\ComponentAccessGuard;

/**
 * Enforces the admin guard for restricted components on EVERY mount and hydrate,
 * regardless of how the component was reached — its own route, the modal stack or
 * the generic component page. Because the check lives here (a noerd component
 * hook), the modal system needs no knowledge of noerd's authorization: it just
 * mounts a component and this hook rejects an unauthorized one. It attaches to
 * every component (no TRAITS): a restricted component need not be a noerd one.
 */
final class ComponentAccessHook extends NoerdComponentHook
{
    public function boot(): void
    {
        ComponentAccessGuard::authorize($this->component->getName());
    }
}
