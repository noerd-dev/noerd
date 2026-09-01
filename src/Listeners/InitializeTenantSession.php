<?php

namespace Noerd\Listeners;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;

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

        if (! TenantHelper::hasTenant()) {
            $savedTenantId = $user->setting->selected_tenant_id;

            if ($savedTenantId && $user->tenants->contains('id', $savedTenantId)) {
                TenantHelper::setSelectedTenantId($savedTenantId);
            } else {
                $firstTenant = $user->tenants->first();
                if ($firstTenant) {
                    TenantHelper::setSelectedTenantId($firstTenant->id);
                }
            }
        }
    }
}
