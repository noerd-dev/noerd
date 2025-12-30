<?php

use Livewire\Volt\Component;

new class extends Component {
    public function openApp(string $appName, string $route): void
    {
        auth()->user()->update(['selected_app' => $appName]);
        $this->redirect(route($route), navigate: true);
    }

    public function openSidebar(): void
    {
        if (session('hide_sidebar')) {
            session()->forget('hide_sidebar');
        } else {
            session(['hide_sidebar' => true]);
        }
    }
}; ?>

@inject('navigation', 'Noerd\Noerd\Services\NavigationService')

<div>
    <!-- Mobile Overlay Background (nur <xl) -->
    <div x-show="showSidebar" x-transition.opacity class="xl:hidden fixed inset-0 z-50 bg-gray-900/80"
         @click="showSidebar = false; $wire.openSidebar()"></div>

    <!-- Mobile Close Button (nur <xl) -->
    <div x-show="showSidebar" x-transition class="xl:hidden fixed top-6 left-[376px] z-50">
        <button @click="showSidebar = false; $wire.openSidebar()" type="button" class="-m-2.5 p-2.5">
            <span class="sr-only">Close sidebar</span>
            <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                 stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <!-- Sidebar -->
    <div class="flex">

        <!-- First column sidebar / Apps -->
        <div x-show="showSidebar" :class="showSidebar ? 'xl:w-20' : 'xl:w-12.5'" @class([
                'bg-brand-navi border-r pt-[8px] border-gray-300 my-0 transition-[width] fixed inset-y-0 z-50 xl:z-40 flex flex-col w-20'
            ])>
            <div class="text-xs text-center overflow-y-auto flex-1">
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
                                'stroke-black border-transparent hover:border-gray-500!' }}
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

            <div x-show="showSidebar" :class="showSidebar ? 'xl:w-69.75 ml-20' : 'ml-12.5'" @class([
            'fixed inset-y-0 z-50 xl:z-40 bg-brand-navi flex flex-col w-69.75',
        ])>
                <livewire:layout.sidebar-navigation :navigation="$navigation->subMenu()"
                                                    :navigations="$navigation->blockMenus()"/>
            </div>
        @endif
    </div>
</div>
