<?php

declare(strict_types=1);

namespace Noerd\Livewire\Hooks;

use Noerd\Support\TenantContext;

/**
 * Marks the span in which a Livewire component processes a CLIENT request —
 * from hydration (the browser sent an update or an action) to dehydration.
 * Inside it, the tenant guard of BelongsToTenant refuses to write a record
 * into a tenant other than the signed-in user's: a crafted form payload must
 * never move a record into a foreign tenant. Every Livewire component is
 * covered, not only noerd's — a module component mass-assigning its form is
 * the case the guard exists for. Mounting (the first render) is no client
 * request and stays unmarked.
 *
 * @internal
 */
final class TenantWriteGuardHook extends NoerdComponentHook
{
    private bool $entered = false;

    public function hydrate(mixed $memo = null): void
    {
        TenantContext::enterClientRequest();
        $this->entered = true;
    }

    public function dehydrate(mixed ...$params): void
    {
        $this->leave();
    }

    public function exception(mixed ...$params): void
    {
        $this->leave();
    }

    private function leave(): void
    {
        if ($this->entered) {
            TenantContext::leaveClientRequest();
            $this->entered = false;
        }
    }
}
