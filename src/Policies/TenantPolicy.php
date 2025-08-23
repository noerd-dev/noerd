<?php

namespace Noerd\Noerd\Policies;

use Illuminate\Support\Facades\Auth;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;

class TenantPolicy
{
    /**
     * Check if user can access orders functionality
     */
    public function orders(User $user): bool
    {
        // Admin users have access to all orders
        if ($user->isAdmin()) {
            return true;
        }

        // Check if current tenant has order-related apps
        $tenant = Tenant::find($user->selected_tenant_id);
        
        if (!$tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        // Apps that provide order functionality
        $orderApps = ['DELIVERY', 'RESTAURANT', 'STORE', 'CANTEEN'];

        return (bool) array_intersect($activeApps, $orderApps);
    }

    /**
     * Check if user can access times functionality
     */
    public function times(User $user): bool
    {
        // Admin users have access to all times functionality
        if ($user->isAdmin()) {
            return true;
        }

        // Check if current tenant has time-related apps
        $tenant = Tenant::find($user->selected_tenant_id);
        
        if (!$tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        // Apps that provide time functionality
        $timeApps = ['DELIVERY', 'RESTAURANT', 'STORE'];

        return (bool) array_intersect($activeApps, $timeApps);
    }

    /**
     * Check if tenant has just menu module (for display logic)
     */
    public function justMenuModule(User $user): bool
    {
        $tenant = Tenant::find($user->selected_tenant_id);
        
        if (!$tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        // Return true only if MENU is the only app
        return count($activeApps) === 1 && in_array('MENU', $activeApps);
    }

    /**
     * Check if user can access website functionality (CMS)
     */
    public function website(User $user): bool
    {
        // Check if current tenant has CMS app (even for admins)
        $tenant = Tenant::find($user->selected_tenant_id);
        
        if (!$tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        // Only allow access if tenant actually has CMS app
        return in_array('CMS', $activeApps);
    }

    /**
     * Check if user has general access to tenant functionality
     */
    public function access(User $user): bool
    {
        // Admin users always have access
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user has access to the selected tenant
        return $user->tenants->contains('id', $user->selected_tenant_id);
    }
}
