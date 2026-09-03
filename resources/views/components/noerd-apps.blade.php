<?php

use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\TenantApp;

new class extends Component {
    public function mount(): void
    {
        $user = NoerdAuth::user();
        $selectedTenantId = TenantHelper::getSelectedTenantId();

        // Fall back to an available tenant when none is selected, or when the user
        // is no longer assigned to the currently selected one. Guarded so the modal
        // re-mount of this component never writes the session on every stack update.
        if (! $selectedTenantId || ! $user->canAccessTenant($selectedTenantId)) {
            TenantHelper::setSelectedTenantId($user->accessibleTenants()->first()?->id);
        }
    }

    /**
     * The target is resolved from the tenant's own app record — never from the
     * request. A client-supplied route would otherwise redirect anywhere.
     */
    public function openApp(string $appName): void
    {
        $tenantApp = TenantHelper::getSelectedTenant()?->tenantApps
            ->first(fn(TenantApp $app): bool => mb_strtolower($app->name) === mb_strtolower($appName));

        abort_unless($tenantApp instanceof TenantApp, 403);
        abort_if((bool) $tenantApp->pivot->is_hidden, 403);
        abort_unless((bool) $tenantApp->is_active, 403);
        abort_unless(AccessHelper::canAccessApp($tenantApp->name), 403);
        abort_unless(Route::has((string) $tenantApp->route), 404);

        TenantHelper::setSelectedApp($tenantApp->name);
        $this->redirect(route((string) $tenantApp->route), navigate: true);
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
                                    class="{{ $selectedApp === $tenantApp->name  ? 'stroke-brand-primary border-brand-primary' :
                                'stroke-black border-transparent hover:border-gray-500!' }}
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
