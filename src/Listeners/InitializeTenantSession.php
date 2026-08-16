<?php

namespace Noerd\Listeners;

use Illuminate\Auth\Events\Login;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;

class InitializeTenantSession
{
    public function handle(Login $event): void
    {
        // The Login event fires for every guard — only react to noerd logins
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
