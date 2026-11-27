<?php

use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Services\ComponentSlotsRegistry;
use Noerd\Traits\OpensTenantApps;

new class extends Component {
    use OpensTenantApps;

    /**
     * Components modules registered for the apps overview (`ComponentSlotsRegistry::APPS_OVERVIEW`),
     * rendered above the app tiles. Each one decides itself whether it renders anything.
     *
     * @var list<string>
     */
    #[Locked]
    public array $overviewComponents = [];

    public function mount(): void
    {
        $this->overviewComponents = app(ComponentSlotsRegistry::class)->get(ComponentSlotsRegistry::APPS_OVERVIEW);

        $user = NoerdAuth::user();
        $selectedTenantId = TenantHelper::getSelectedTenantId();

        // Fall back to an available tenant when none is selected, or when the user
        // is no longer assigned to the currently selected one. Guarded so the modal
        // re-mount of this component never writes the session on every stack update.
        if (! $selectedTenantId || ! $user->canAccessTenant($selectedTenantId)) {
            TenantHelper::setSelectedTenantId($user->accessibleTenants()->first()?->id);
        }
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Home') }}</x-noerd::modal-title>
    </x-slot:header>

    @php
        $selectedTenant = TenantHelper::getSelectedTenant();
        $selectedApp = TenantHelper::getSelectedApp();
    @endphp

    @foreach($overviewComponents as $overviewComponent)
        @livewire($overviewComponent, [], key('apps-overview-'.$overviewComponent))
    @endforeach

    <div class="mb-12">
        <div class="flex flex-wrap">
            @foreach($selectedTenant?->tenantApps ?? [] as $tenantApp)
                @continue($tenantApp->pivot->is_hidden)
                @continue(! AccessHelper::canAccessApp($tenantApp->name))
                <button type="button"
                        wire:key="app-{{ $tenantApp->id }}"
                        @if($tenantApp->is_active)
                            wire:click="openApp({{ Illuminate\Support\Js::from($tenantApp->name) }})"
                        @else
                            disabled
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
                                    class="{{ $selectedApp === $tenantApp->name ? 'stroke-brand-primary' : 'stroke-black' }}"/>
                        </div>

                        <div @class([
                            'text-gray-500 w-full',
                            'text-gray-400' => !$tenantApp->is_active
                        ])>
                            {{ $tenantApp->title }}
                        </div>

                        @if(!$tenantApp->is_active)
                            <div class="text-xs text-gray-400 mt-1">
                                {{ __('Inactive') }}
                            </div>
                        @endif
                    </div>
                </button>
            @endforeach
        </div>
    </div>

    <livewire:noerd::layout.dashboard-widgets />
</x-noerd::page>
