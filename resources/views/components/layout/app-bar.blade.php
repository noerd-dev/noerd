<?php

use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\TenantApp;

new class extends Component {
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

    public function openHome(): void
    {
        TenantHelper::setSelectedApp('noerd-apps');
        $this->redirect(route('noerd.apps'), navigate: true);
    }
}; ?>

<div>
    @if (NoerdAuth::check())
        <div x-show="showAppbar && (isDesktop || showSidebar)"
             x-transition
             @class([
                'bg-brand-navi border-r pt-[8px] border-gray-300 my-0 transition-[width] fixed top-0 lg:top-[calc(var(--banner-height,0px)_+_var(--impersonation-banner-height,0px)_+_var(--environment-banner-height,0px))] bottom-0 z-50 lg:z-40 flex flex-col'
            ])
             :style="'width: var(--sidebar-apps-width)'"
        >
            @php
                $selectedTenant = TenantHelper::getSelectedTenant();
                $selectedApp = TenantHelper::getSelectedApp();
            @endphp
            <div class="text-xs text-center overflow-y-auto flex-1 pb-6">
                {{-- Home --}}
                <button type="button" wire:click="openHome" class="cursor-pointer w-full">
                    <div @class([
                        'bg-brand-primary/5! border-brand-primary!' => $selectedApp === 'noerd-apps',
                        'hover:bg-brand-navi-hover flex mt-2 h-[40px] w-[40px] rounded-full mx-auto',
                    ])>
                        <div class="my-auto flex-1">
                            <x-icon name="home" class="mx-auto w-5 h-5 {{ $selectedApp === 'noerd-apps' ? 'text-gray-900' : 'text-gray-600' }}" />
                        </div>
                    </div>
                </button>
                <div class="border-b border-gray-300 mx-4 mt-3 mb-1"></div>

                @foreach($selectedTenant?->tenantApps ?? [] as $tenantApp)
                    @continue($tenantApp->pivot->is_hidden)
                    @continue(! AccessHelper::canAccessApp($tenantApp->name))
                    <button type="button" wire:key="app-{{ $tenantApp->id }}"
                            @if($tenantApp->is_active)
                                wire:click="openApp({{ Illuminate\Support\Js::from($tenantApp->name) }})"
                                class="cursor-pointer w-full"
                            @else
                                disabled class="opacity-50 w-full"
                            @endif>
                        <div
                            @class(['bg-brand-primary/5!  border-brand-primary!' => $selectedApp === $tenantApp->name,
                                        'hover:bg-brand-navi-hover flex mt-4 h-[45px] w-[45px] rounded-sm  mx-auto'])>
                            @if($tenantApp->icon)
                                <x-noerd::app-icon
                                    :icon="$tenantApp->icon"
                                    class="{{ $selectedApp === $tenantApp->name  ? 'stroke-black border-brand-primary' :
                                'stroke-black border-transparent hover:border-gray-500!' }}
                                    border-l-2"/>
                            @endif
                        </div>
                        <div class="text-gray-900 text-[11px] mt-1">{{$tenantApp->title}}</div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
