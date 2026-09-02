<?php

use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;

new class extends Component {
    public function openApp(string $appName, string $route): void
    {
        TenantHelper::setSelectedApp($appName);
        $this->redirect(route($route), navigate: true);
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
                <a wire:click="openApp('noerd-apps', 'noerd.apps')" class="cursor-pointer">
                    <div @class([
                        'bg-brand-primary/5! border-brand-primary!' => $selectedApp === 'noerd-apps',
                        'hover:bg-brand-navi-hover flex mt-2 h-[40px] w-[40px] rounded-full mx-auto',
                    ])>
                        <div class="my-auto flex-1">
                            <x-icon name="home" class="mx-auto w-5 h-5 {{ $selectedApp === 'noerd-apps' ? 'text-gray-900' : 'text-gray-600' }}" />
                        </div>
                    </div>
                </a>
                <div class="border-b border-gray-300 mx-4 mt-3 mb-1"></div>

                @foreach($selectedTenant?->tenantApps ?? [] as $tenantApp)
                    @continue($tenantApp->pivot->is_hidden)
                    @continue(! \Noerd\Helpers\AccessHelper::canAccessApp($tenantApp->name))
                    <a @if($tenantApp->is_active)
                           wire:click="openApp('{{$tenantApp->name}}', '{{$tenantApp->route}}')"
                       class="cursor-pointer"
                       @else class="opacity-50" @endif>
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
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
