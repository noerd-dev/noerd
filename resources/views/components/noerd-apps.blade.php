<?php

use Livewire\Component;
use Illuminate\Support\Str;
use Noerd\Helpers\TenantHelper;
use Noerd\Helpers\NoerdAuth;

new class extends Component {

    public function mount(): void
    {
        $user = NoerdAuth::user();
        $selectedTenantId = TenantHelper::getSelectedTenantId();

        // Fall back to an available tenant when none is selected, or when the user
        // is no longer assigned to the currently selected one.
        if (! $selectedTenantId || ! $user->tenants->contains('id', $selectedTenantId)) {
            TenantHelper::setSelectedTenantId($user->tenants->first()?->id);
        }
    }

    public function openApp(string $appName, string $route): void
    {
        TenantHelper::setSelectedApp($appName);
        $this->redirect(route($route), navigate: true);
    }

} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Home</x-noerd::modal-title>
    </x-slot:header>

    @php
        $selectedTenant = TenantHelper::getSelectedTenant();
        $selectedApp = TenantHelper::getSelectedApp();
    @endphp

    <div class="mb-12">
        <div class="flex flex-wrap">
            @foreach($selectedTenant?->tenantApps ?? [] as $tenantApp)
                @continue($tenantApp->pivot->is_hidden)
                @continue(! \Noerd\Helpers\AccessHelper::canAccessApp($tenantApp->name))
                <a @if($tenantApp->is_active)
                       wire:click="openApp('{{ $tenantApp->name }}', '{{ $tenantApp->route }}')"
                   @else
                       href="#/"
                        @endif
                        @class([
                            'bg-white border border-gray-300 hover:bg-gray-50 w-36 h-36 mr-6 mt-6 flex p-2 py-4 text-sm text-center rounded-lg items-center justify-center',
                            'cursor-pointer' => $tenantApp->is_active,
                            'opacity-50 cursor-not-allowed' => !$tenantApp->is_active
                        ])>
                    <div class="m-auto">
                        <div class="inline-block mb-2">
                            <x-noerd::app-icon
                                    :icon="$tenantApp->icon"
                                    class="{{ $selectedApp === $tenantApp->name  ? 'stroke-brand-primary border-brand-primary' :
                                'stroke-black border-transparent hover:!border-gray-500' }}
                                border-l-2"/>
                        </div>

                        <div @class([
                            'text-gray-500 w-full',
                            'text-gray-400' => !$tenantApp->is_active
                        ])>
                            {{ $tenantApp->title }}
                        </div>

                        @if(!$tenantApp->is_active)
                            <div class="text-xs text-gray-400 mt-1">
                                Inaktiv
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <livewire:noerd::layout.dashboard-widgets />
</x-noerd::page>
