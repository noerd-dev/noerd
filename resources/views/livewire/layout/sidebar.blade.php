<?php

use Livewire\Volt\Component;

new class extends Component {
    public function openApp(string $appName, string $route): void
    {
        auth()->user()->update(['selected_app' => $appName]);
        $this->redirect(route($route), navigate: true);
    }
}; ?>

@inject('navigation', 'Noerd\Noerd\Services\NavigationService')

<div>
    <!-- Static sidebar for mobile -->
    <div x-show="open" x-transition class="fixed top-6 z-50 lg:hidden" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/80"></div>
        <div class="fixed inset-0 flex">
            <div class="relative flex bg-white">
                <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                    <button x-on:click="open = ! open" type="button" class="-m-2.5 p-2.5">
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <livewire:layout.sidebar-navigation :navigation="$navigation->subMenu()"
                                                    :navigations="$navigation->blockMenus()"/>
            </div>
        </div>
    </div>

    <!-- Static sidebar for desktop -->
    <div class="flex">

        <!-- First column sidebar / Apps -->
        <div x-show="showSidebar" :class="showSidebar ? 'lg:w-[80px]' : 'lg:w-[50px]'" @class([
                'hidden bg-brand-navi border-r pt-[8px] border-gray-300 my-0 transition-[width] lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:flex-col flex'
            ])>
            <div class="text-xs text-center">
                {{--
                <a wire:navigate href="{{ route('noerd-home') }}">
                    <div class="px-6 pt-4 pb-3">
                        <x-noerd::app-logo-icon></x-noerd::app-logo-icon>
                    </div>
                </a>
                --}}

                @foreach(auth()->user()->selectedTenant()?->tenantApps as $tenantApp)
                    <a @if($tenantApp->is_active)
                           wire:click="openApp('{{$tenantApp->name}}', '{{$tenantApp->route}}')"
                       class="cursor-pointer"
                       @else class="opacity-50" @endif>
                        <div
                            @class(['!bg-brand-primary/5  border-brand-primary!' => auth()->user()?->selected_app === $tenantApp->name,
                                        'hover:bg-brand-navi-hover flex mt-4 h-[45px] w-[45px] rounded-sm  mx-auto'])>
                            @if($tenantApp->icon)
                                <x-noerd::app-icon
                                    :icon="$tenantApp->icon"
                                    class="{{ auth()->user()?->selected_app === $tenantApp->name  ? 'stroke-brand-primary border-brand-primary' :
                                'stroke-black border-transparent hover:!border-gray-500' }}
                                    border-l-2" />
                            @endif
                        </div>
                        <div x-show="showSidebar" class="text-gray-900 text-[11px] mt-1">{{$tenantApp->title}}</div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Second column sidebar / Navigation -->
        @if(count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)

            <div x-show="showSidebar" :class="showSidebar ? 'lg:w-[279px] ml-[80px]' : 'ml-[50px]'" @class([
            'hidden lg:fixed lg:inset-y-0 lg:z-40 bg-brand-navi lg:flex lg:flex-col flex ',
        ])>
                <livewire:layout.sidebar-navigation :navigation="$navigation->subMenu()"
                                                    :navigations="$navigation->blockMenus()"/>
            </div>
        @endif
    </div>
</div>
