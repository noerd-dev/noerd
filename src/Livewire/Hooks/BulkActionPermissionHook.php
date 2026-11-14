<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Noerd\Traits\NoerdList;

/**
 * Refuses a browser call to a list's bulk action the object permissions strip
 * for the current user (ListPermissions::strip(): `deleteSelected` needs
 * DELETE, every other bulk action WRITE). The button is hidden already; this
 * closes the action itself — a module's bulk method needs no guard of its own.
 *
 * @internal
 */
final class BulkActionPermissionHook extends NoerdComponentHook
{
    protected const TRAITS = [NoerdList::class];

    public function call($method, $params, $returnEarly, $metadata, $componentContext): void
    {
        if (! is_string($method) || str_starts_with($method, '$')) {
            return;
        }

        $refuses = (fn(): bool => $this->refusesBulkAction($method))->call($this->component);

        abort_if($refuses, 403);
    }
}
