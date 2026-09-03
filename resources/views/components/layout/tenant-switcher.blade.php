<?php

use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;

new class extends Component {
    /** @return Collection<int, Tenant> */
    #[Computed]
    public function tenants(): Collection
    {
        return NoerdAuth::user()->accessibleTenants();
    }

    public function switchTenant(int $tenantId): void
    {
        // Membership for everybody, every tenant of the installation for a
        // super admin — the same answer the per-request membership check gives.
        abort_unless(NoerdAuth::user()->canAccessTenant($tenantId), 403);

        TenantHelper::setSelectedTenantId($tenantId);

        $redirectUrl = '/';
        $referer = request()->header('Referer');

        if ($referer) {
            // Only the PATH (+ query) of the referer is ever redirected to — the
            // header is client-supplied, so its origin is never trusted.
            $path = (string) parse_url($referer, PHP_URL_PATH);
            $query = parse_url($referer, PHP_URL_QUERY);
            $sameSiteUrl = '/' . ltrim($path, '/') . ($query ? '?' . $query : '');
            $segments = explode('/', mb_trim($path, '/'));
            $appPrefix = $segments[0] ?? null;

            if ($appPrefix) {
                // System paths that are always accessible: the setup area,
                // the apps dashboard and every core screen under the noerd
                // URL prefix (user, no-tenant, ...).
                $systemPaths = ['setup', 'noerd-apps', mb_trim(config('noerd.routes.prefix', 'noerd'), '/')];

                if (in_array($appPrefix, $systemPaths, true)) {
                    $redirectUrl = $sameSiteUrl;
                } else {
                    $newTenant = Tenant::find($tenantId);
                    $hasApp = $newTenant?->tenantApps()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($appPrefix)])
                        ->exists();

                    if ($hasApp) {
                        $redirectUrl = $sameSiteUrl;
                    }
                }
            }
        }

        $this->redirect($redirectUrl);
    }
} ?>

@php
    $selectedTenantId = TenantHelper::getSelectedTenantId();
    $currentTenantName = $this->tenants->firstWhere('id', $selectedTenantId)?->name ?? __('Tenant');
    $canCreateTenant = NoerdAuth::user()->isAdmin()
        && config('noerd.features.multi_tenant')
        && config('noerd.features.new_tenant');
@endphp

<x-noerd::action-menu align="left" width="w-56" wrapperClass="relative hidden lg:flex" panelClass="top-full">
    <x-slot:trigger>
        <x-noerd::button variant="pill"
                         icon="building-office-2"
                         @click="open = ! open"
                         title="{{ __('Switch Tenant') }}">
            <span class="max-w-[12rem] truncate">{{ $currentTenantName }}</span>
            <x-icon name="chevron-down" mini class="ml-1 h-4 w-4 shrink-0" />
        </x-noerd::button>
    </x-slot:trigger>

    @foreach($this->tenants as $tenant)
        <x-noerd::action-menu-item
            wire:key="tenant-{{ $tenant->id }}"
            wire:click="switchTenant({{ $tenant->id }})"
            :active="$tenant->id === $selectedTenantId"
            class="justify-between"
        >
            <span class="truncate">{{ $tenant->name }}</span>
            @if($tenant->id === $selectedTenantId)
                <x-icon name="check" mini class="h-4 w-4 shrink-0 text-gray-500" />
            @endif
        </x-noerd::action-menu-item>
    @endforeach

    @if($canCreateTenant)
        <x-noerd::action-menu-separator/>

        <x-noerd::action-menu-item :href="route('noerd.create-tenant')" navigate>
            <x-icon name="plus" mini class="h-4 w-4 shrink-0" />
            {{ __('New Tenant') }}
        </x-noerd::action-menu-item>
    @endif
</x-noerd::action-menu>
