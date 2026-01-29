<?php

namespace Noerd\Listeners;

use Illuminate\Auth\Events\Login;
use Noerd\Helpers\TenantHelper;

class InitializeTenantSession
{
    public function handle(Login $event): void
    {
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
