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

    public function saveSidebarWidth(string $width): void
    {
        session(['sidebar_nav_width' => $width]);
    }

    public function toggleAppbar(): void
    {
        if (session('hide_appbar')) {
            session()->forget('hide_appbar');
        } else {
            session(['hide_appbar' => true]);
        }
    }
}; ?>

@inject('navigation', 'Noerd\Noerd\Services\NavigationService')

<div>
    <!-- Mobile Overlay Background (nur <xl) -->
    <div x-show="showSidebar" x-transition.opacity class="xl:hidden fixed inset-0 z-50 bg-gray-900/80"
         @click="showSidebar = false; $wire.openSidebar()"></div>

    <!-- Mobile Close Button (nur <xl) -->
    <div x-show="showSidebar" x-transition class="xl:hidden fixed top-4 right-4 z-50">
        <button @click="showSidebar = false; $wire.openSidebar()" type="button" class="p-2 bg-black/50 rounded-full">
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
        <div x-show="showSidebar && showAppbar"
             x-transition
             @class([
                'bg-brand-navi border-r pt-[8px] border-gray-300 my-0 transition-[width] fixed inset-y-0 z-50 xl:z-40 flex flex-col'
            ])
            :style="'width: var(--sidebar-apps-width)'"
        >
            <div class="text-xs text-center overflow-y-auto flex-1">
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

            <div x-show="showSidebar"
                 x-data="{
                    isResizing: false,
                    startX: 0,
                    startWidth: 0
                 }"
                 @mousemove.window="if (isResizing) {
                    const diff = $event.clientX - startX;
                    const newWidth = Math.max(200, Math.min(500, startWidth + diff));
                    document.documentElement.style.setProperty('--sidebar-nav-width', newWidth + 'px');
                 }"
                 @mouseup.window="if (isResizing) {
                    isResizing = false;
                    const width = getComputedStyle(document.documentElement).getPropertyValue('--sidebar-nav-width').trim();
                    $wire.saveSidebarWidth(width);
                 }"
                 @class([
                    'fixed inset-y-0 z-50 xl:z-40 bg-brand-navi flex flex-col border-r border-gray-300',
                 ])
                 :style="'width: var(--sidebar-nav-width); margin-left: ' + (showAppbar ? 'var(--sidebar-apps-width)' : '0')"
            >
                <livewire:layout.sidebar-navigation :navigation="$navigation->subMenu()"
                                                    :navigations="$navigation->blockMenus()"/>

                <!-- Toggle Appbar Button -->
                <button @click="showAppbar = !showAppbar; $wire.toggleAppbar()"
                        class="mt-auto p-3 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                         class="w-5 h-5 transition-transform duration-200"
                         :class="showAppbar ? '' : 'rotate-180'">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <!-- Resize Handle -->
                <div class="absolute right-0 top-0 h-full w-0.5 cursor-col-resize hover:bg-brand-primary/40 transition-all"
                     @mousedown="isResizing = true; startX = $event.clientX; startWidth = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-nav-width'))"></div>
            </div>
        @endif
    </div>
</div>
