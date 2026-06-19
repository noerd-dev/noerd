<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;

new class () extends Component {
    public function switchTenant(int $tenantId): void
    {
        $user = Auth::user();
        $accessToClientsIds = $user->tenants->pluck('id')->toArray();

        if (! in_array($tenantId, $accessToClientsIds)) {
            return;
        }

        TenantHelper::setSelectedTenantId($tenantId);

        $redirectUrl = '/';
        $referer = request()->header('Referer');

        if ($referer) {
            $path = parse_url($referer, PHP_URL_PATH);
            $segments = explode('/', mb_trim($path, '/'));
            $appPrefix = $segments[0] ?? null;

            if ($appPrefix) {
                // System paths that are always accessible
                $systemPaths = ['setup', 'profile', 'dashboard', 'no-tenant'];

                if (in_array($appPrefix, $systemPaths)) {
                    $redirectUrl = $referer;
                } else {
                    $newTenant = Tenant::find($tenantId);
                    $hasApp = $newTenant?->tenantApps()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($appPrefix)])
                        ->exists();

                    if ($hasApp) {
                        $redirectUrl = $referer;
                    }
                }
            }
        }

        $this->redirect($redirectUrl);
    }
} ?>

@php
    $tenants = auth()->user()->tenants;
    $selectedTenantId = \Noerd\Helpers\TenantHelper::getSelectedTenantId();
    $currentTenantName = $tenants->firstWhere('id', $selectedTenantId)?->name ?? __('Tenant');
    $canCreateTenant = auth()->user()->isAdmin()
        && config('noerd.features.multi_tenant')
        && config('noerd.features.new_tenant');
@endphp

<div x-data="{ open: false }" class="relative hidden lg:flex">
    <x-noerd::button variant="pill"
                     icon="building-office-2"
                     @click="open = ! open"
                     x-on:click.outside="open = false"
                     title="{{ __('Switch Tenant') }}">
        <span class="max-w-[12rem] truncate">{{ $currentTenantName }}</span>
        <svg class="ml-1 h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
        </svg>
    </x-noerd::button>

    <div x-show="open" x-transition x-cloak
         class="absolute left-0 z-90 mt-2 w-56 origin-top-left rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-hidden"
         style="top: 100%"
         role="menu" aria-orientation="vertical">
        @foreach($tenants as $tenant)
            <button type="button" wire:click="switchTenant({{ $tenant->id }})"
                    @class([
                        'flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50',
                        'font-medium' => $tenant->id === $selectedTenantId,
                    ])
                    role="menuitem">
                <span class="truncate">{{ $tenant->name }}</span>
                @if($tenant->id === $selectedTenantId)
                    <svg class="h-4 w-4 shrink-0 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                    </svg>
                @endif
            </button>
        @endforeach

        @if($canCreateTenant)
            <div class="my-1 border-t border-gray-100"></div>
            <a wire:navigate href="{{ route('create-tenant') }}"
               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
               role="menuitem">
                <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/>
                </svg>
                {{ __('New Tenant') }}
            </a>
        @endif
    </div>
</div>
