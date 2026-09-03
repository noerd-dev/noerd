<?php

use Livewire\Component;
use Noerd\Support\LayoutState;
use Noerd\Helpers\TenantHelper;

new class extends Component {
    public function openHome(): void
    {
        TenantHelper::setSelectedApp('noerd-apps');
        $this->redirect(route('noerd.apps'), navigate: true);
    }

    public function openSidebar(): void
    {
        LayoutState::setSidebarVisible(! LayoutState::sidebarVisible());
    }

    /**
     * The stored width is interpolated into an inline <style> block by the app
     * layout, so only a plain pixel value is ever accepted.
     */
    public function saveSidebarWidth(string $width): void
    {
        abort_unless(preg_match('/^\d{2,4}px$/', $width) === 1, 422);

        LayoutState::setNavigationWidth($width);
    }

    public function toggleAppbar(): void
    {
        LayoutState::setAppBarVisible(! LayoutState::appBarVisible());
    }
}; ?>

@inject('navigation', 'Noerd\Services\NavigationService')

<div>
    {{-- Mobile overlay background (below xl only) --}}
    <div x-show="showSidebar" x-transition.opacity class="lg:hidden fixed inset-0 z-50 bg-gray-900/80"
         @click="showSidebar = false"></div>

    {{-- Mobile close button (below xl only) --}}
    <div x-show="showSidebar" x-transition class="lg:hidden fixed top-4 right-4 z-50">
        <x-noerd::button variant="icon" icon="x-mark" @click="showSidebar = false" type="button" class="bg-black/50! text-white!">
            <span class="sr-only">{{ __('Close sidebar') }}</span>
        </x-noerd::button>
    </div>

    <!-- Sidebar -->
    <div class="flex">

        <!-- First column sidebar / Apps -->
        <livewire:noerd::layout.app-bar />

        <!-- Second column sidebar / Navigation -->
        @if(count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)

            <div x-show="showSidebar"
                 x-data="noerdSidebarResize(@js(['min' => 200, 'max' => 500, 'step' => 16]))"
                 @mousemove.window="move($event)"
                 @mouseup.window="stop()"
                 @class([
                    'fixed top-0 lg:top-[calc(var(--banner-height,0px)_+_var(--impersonation-banner-height,0px)_+_var(--environment-banner-height,0px))] bottom-0 z-50 lg:z-40 bg-brand-navi flex flex-col border-r border-gray-300',
                 ])
                 :style="'width: var(--sidebar-nav-width); margin-left: ' + (showAppbar ? 'var(--sidebar-apps-width)' : '0')"
            >
                <livewire:noerd::layout.sidebar-navigation :navigation="$navigation->subMenu()"
                                                    :navigations="$navigation->blockMenus()"/>

                <!-- Toggle Appbar Button -->
                <div class="border-gray-200 border-t mt-auto flex items-center">
                    {{-- Home shortcut — only visible while the app bar is hidden --}}
                    <button x-show="! showAppbar" x-transition.opacity wire:click="openHome" type="button"
                            class="p-3 text-gray-400 hover:text-gray-600 transition-colors">
                        <span class="sr-only">{{ __('Home') }}</span>
                        <x-icon name="home" class="w-5 h-5" />
                    </button>

                    {{-- Only appbar toggle. Persists on desktop only — on mobile the appbar is
                         transient and hidden again after every navigation --}}
                    <button @click="showAppbar = !showAppbar; if (isDesktop) { $wire.toggleAppbar() }"
                            type="button"
                            aria-label="{{ __('Toggle app bar') }}"
                            class="p-3 text-gray-400 hover:text-gray-600 transition-colors">
                        <x-icon name="chevron-left" class="w-5 h-5 transition-transform duration-200" x-bind:class="showAppbar ? '' : 'rotate-180'" />
                    </button>
                </div>
                <!-- Resize Handle -->
                <div
                    role="separator"
                    aria-orientation="vertical"
                    aria-label="{{ __('Resize navigation') }}"
                    aria-valuemin="200"
                    aria-valuemax="500"
                    :aria-valuenow="width"
                    tabindex="0"
                    class="absolute right-0 top-0 h-full w-0.5 cursor-col-resize hover:bg-brand-primary/40 transition-all focus:bg-brand-primary/40 focus:outline-none"
                    @mousedown="start($event)"
                    @keydown.arrow-left.prevent="nudge(-1)"
                    @keydown.arrow-right.prevent="nudge(1)"></div>
            </div>
        @endif
    </div>
</div>
