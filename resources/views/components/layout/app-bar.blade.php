<?php

use Livewire\Component;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\TenantApp;

new class extends Component {
    public function openApp(string $appName, string $route): void
    {
        TenantHelper::setSelectedApp($appName);
        $this->redirect(route($route), navigate: true);
    }
}; ?>

<div>
    @auth
        <div x-show="showSidebar && showAppbar"
             x-transition
             @class([
                'bg-brand-navi border-r pt-[8px] border-gray-300 my-0 transition-[width] fixed top-[calc(var(--banner-height,0px)_+_var(--impersonation-banner-height,0px))] bottom-0 z-50 xl:z-40 flex flex-col'
            ])
             :style="'width: var(--sidebar-apps-width)'"
        >
            @php
                $selectedTenant = TenantHelper::getSelectedTenant();
                $selectedApp = TenantHelper::getSelectedApp();
            @endphp
            <div class="text-xs text-center overflow-y-auto flex-1 pb-6">
                @foreach($selectedTenant?->tenantApps ?? [] as $tenantApp)
                    @continue($tenantApp->pivot->is_hidden)
                    <a @if($tenantApp->is_active)
                           wire:click="openApp('{{$tenantApp->name}}', '{{$tenantApp->route}}')"
                       class="cursor-pointer"
                       @else class="opacity-50" @endif>
                        <div
                            @class(['!bg-brand-primary/5  border-brand-primary!' => $selectedApp === $tenantApp->name,
                                        'hover:bg-brand-navi-hover flex mt-4 h-[45px] w-[45px] rounded-sm  mx-auto'])>
                            @if($tenantApp->icon)
                                <x-noerd::app-icon
                                    :icon="$tenantApp->icon"
                                    class="{{ $selectedApp === $tenantApp->name  ? 'stroke-brand-primary border-brand-primary' :
                                'stroke-black border-transparent hover:border-gray-500!' }}
                                    border-l-2"/>
                            @endif
                        </div>
                        <div x-show="showSidebar" class="text-gray-900 text-[11px] mt-1">{{$tenantApp->title}}</div>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        @php
            $publicApps = TenantApp::where('is_active', true)
                ->where('is_public', true)
                ->get();
        @endphp
        @if($publicApps->count() > 1)
            <div x-show="showSidebar && showAppbar"
                 x-transition
                 @class([
                    'bg-brand-navi border-r pt-[8px] border-gray-300 my-0 transition-[width] fixed top-[calc(var(--banner-height,0px)_+_var(--impersonation-banner-height,0px))] bottom-0 z-50 xl:z-40 flex flex-col'
                ])
                 :style="'width: var(--sidebar-apps-width)'"
            >
                <div class="text-xs text-center overflow-y-auto flex-1 pb-6">
                    @foreach($publicApps as $tenantApp)
                        <a href="{{ route($tenantApp->route) }}" class="cursor-pointer">
                            <div class="hover:bg-brand-navi-hover flex mt-4 h-[45px] w-[45px] rounded-sm mx-auto">
                                @if($tenantApp->icon)
                                    <x-noerd::app-icon
                                        :icon="$tenantApp->icon"
                                        class="stroke-black border-transparent hover:border-gray-500! border-l-2"/>
                                @endif
                            </div>
                            <div x-show="showSidebar" class="text-gray-900 text-[11px] mt-1">{{$tenantApp->title}}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endauth
</div>
