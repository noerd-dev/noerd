<?php

namespace Noerd\Noerd\Policies;

class TenantPolicy
{
    public function orders($user): bool
    {
        $activeApps = $user->selectedTenant()?->tenantApps->pluck('name')->toArray() ?? [];

        return (bool) (array_intersect($activeApps, ['DELIVERY', 'RESTAURANT', 'STORE', 'CANTEEN']));
    }

    public function stores($user): bool
    {
        $tenant = $user->selectedTenant();

        if (! $tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        return (bool) (array_intersect($activeApps, ['STORE']));
    }

    public function times($user): bool
    {
        $tenant = $user->selectedTenant();

        if (! $tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        return (bool) (array_intersect($activeApps, ['STORE', 'DELIVERY']));
    }

    public function gastrofix($user): bool
    {
        $tenant = $user->selectedTenant();

        if (! $tenant) {
            return false;
        }

        return (bool) $tenant->module_gastrofix;
    }

    public function justMenuModule($user): bool
    {
        $tenant = $user->selectedTenant();

        if (! $tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        return (bool) (array_intersect($activeApps, ['MENU']));
    }

    public function pos($user): bool
    {
        $tenant = $user->selectedTenant();

        if (! $tenant) {
            return false;
        }

        return (bool) (array_intersect($tenant->id, [1]));
    }

    public function cms($user): bool
    {
        $tenant = $user->selectedTenant();

        if (! $tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        return (bool) (array_intersect($activeApps, ['CMS']));
    }

    public function website($user): bool
    {
        // Backwards-compatible alias for the CMS ability
        return $this->cms($user);
    }
}
