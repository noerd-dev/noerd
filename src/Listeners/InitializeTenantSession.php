<?php

declare(strict_types=1);

namespace Noerd\Listeners;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;

/**
 * Seeds the tenant session from the user's persisted selection the moment a
 * noerd user is authenticated — on login and on every other way the guard
 * adopts a user (a session-resumed request, a test's actingAs()).
 */
class InitializeTenantSession
{
    public function handle(Login|Authenticated $event): void
    {
        // The events fire for every guard — only react to noerd users
        // (a host or website-guard user has no noerd tenant session).
        if ($event->guard !== NoerdAuth::guardName()) {
            return;
        }

        $user = $event->user;

        // The guard name alone is no proof of the user CLASS (a host may point
        // another provider at the same guard) — and only a NoerdUser carries the
        // tenant API used below.
        if (! $user instanceof NoerdUser) {
            return;
        }

        if (! TenantHelper::hasTenant()) {
            // Read-only: authentication happens on every request, so a missing
            // settings row must never trigger the firstOrCreate write path.
            $savedTenantId = $user->userSetting()->first()?->selected_tenant_id;

            if ($savedTenantId && $user->canAccessTenant($savedTenantId)) {
                TenantHelper::setSelectedTenantId($savedTenantId);
            } else {
                $firstTenant = $user->accessibleTenants()->first();
                if ($firstTenant) {
                    TenantHelper::setSelectedTenantId($firstTenant->id);
                }
            }
        }
    }
}
