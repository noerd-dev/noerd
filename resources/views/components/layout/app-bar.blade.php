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
                'bg-brand-navi border-r pt-[8px] border-gray-300 my-0 transition-[width] fixed top-[calc(var(--banner-height,0px)_+_var(--impersonation-banner-height,0px)_+_var(--environment-banner-height,0px))] bottom-0 z-50 lg:z-40 flex flex-col'
            ])
             :style="'width: var(--sidebar-apps-width)'"
        >
            @php
                $selectedTenant = TenantHelper::getSelectedTenant();
                $selectedApp = TenantHelper::getSelectedApp();
            @endphp
            <div class="text-xs text-center overflow-y-auto flex-1 pb-6">
                {{-- Home --}}
                <a wire:click="openApp('noerd-home', 'noerd-home')" class="cursor-pointer">
                    <div @class([
                        '!bg-brand-primary/5 border-brand-primary!' => $selectedApp === 'noerd-home',
                        'hover:bg-brand-navi-hover flex mt-2 h-[40px] w-[40px] rounded-full mx-auto',
                    ])>
                        <div class="my-auto flex-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                 @class([
                                     'mx-auto w-5 h-5',
                                     'text-gray-900' => $selectedApp === 'noerd-home',
                                     'text-gray-600' => $selectedApp !== 'noerd-home',
                                 ])>
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                        </div>
                    </div>
                </a>
                <div class="border-b border-gray-300 mx-4 mt-3 mb-1"></div>

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
                                    class="{{ $selectedApp === $tenantApp->name  ? 'stroke-black border-brand-primary' :
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
                    'bg-brand-navi border-r pt-[8px] border-gray-300 my-0 transition-[width] fixed top-[calc(var(--banner-height,0px)_+_var(--impersonation-banner-height,0px)_+_var(--environment-banner-height,0px))] bottom-0 z-50 lg:z-40 flex flex-col'
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
