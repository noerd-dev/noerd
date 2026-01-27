<?php

namespace Noerd\Noerd\Listeners;

use Illuminate\Auth\Events\Login;
use Noerd\Noerd\Helpers\TenantHelper;

class InitializeTenantSession
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        // If no tenant is selected in session, choose the first available tenant
        if (! TenantHelper::hasTenant()) {
            $firstTenant = $user->tenants->first();
            if ($firstTenant) {
                TenantHelper::setSelectedTenantId($firstTenant->id);
            }
        }
    }
}
