<?php

namespace Noerd\Noerd\Policies;

use Illuminate\Support\Facades\Auth;
use Noerd\Noerd\Models\Tenant;

class TenantPolicy
{
    public function orders($user): bool
    {
        $activeApps = $user->selectedTenant()?->tenantApps->pluck('name')->toArray() ?? [];

        return (bool) (array_intersect($activeApps, ['DELIVERY', 'RESTAURANT', 'STORE', 'CANTEEN']));
    }

    public function stores(): bool
    {
        $tenant = Tenant::select('id')->where('id', Auth::user()?->selected_tenant_id)->first();

        if (! $tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        return (bool) (array_intersect($activeApps, ['STORE']));
    }

    public function times(): bool
    {
        $tenant = Tenant::select('id')->where('id', Auth::user()?->selected_tenant_id)->first();

        if (! $tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        return (bool) (array_intersect($activeApps, ['STORE', 'DELIVERY']));
    }

    public function gastrofix(): bool
    {
        $tenant = Tenant::select('id', 'module_gastrofix')->where(
            'id',
            Auth::user()?->selected_tenant_id,
        )->first();

        if (! $tenant) {
            return false;
        }

        return (bool) $tenant->module_gastrofix;
    }

    public function justMenuModule(): bool
    {
        $tenant = Tenant::select('id')->where('id', Auth::user()?->selected_tenant_id)->first();

        if (! $tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        return (bool) (array_intersect($activeApps, ['MENU']));
    }

    public function pos(): bool
    {
        $tenant = Tenant::select('id')->where('id', Auth::user()?->selected_tenant_id)->first();

        if (! $tenant) {
            return false;
        }

        return (bool) (array_intersect($tenant->id, [1]));
    }

    public function cms(): bool
    {
        $tenant = Tenant::select('id')->where('id', Auth::user()?->selected_tenant_id)->first();

        if (! $tenant) {
            return false;
        }

        $activeApps = $tenant->tenantApps->pluck('name')->toArray();

        return (bool) (array_intersect($activeApps, ['CMS']));
    }
}
