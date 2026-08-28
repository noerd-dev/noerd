<?php

use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Services\TopBarRegistry;

new class extends Component {
    /**
     * Livewire components contributed by optional modules. Each one decides for
     * itself whether it renders anything.
     *
     * @var array<int, string>
     */
    public array $topBarComponents = [];

    public function mount(): void
    {
        $this->topBarComponents = app(TopBarRegistry::class)->all();
    }

    public function logout(): void
    {
        NoerdAuth::guard()->logout();

        // Drop noerd's session state but do NOT invalidate the whole
        // session: a host guard (e.g. Nova) may share the session cookie.
        Session::forget('noerd');
        Session::forget('impersonating_from');
        Session::regenerate();
        Session::regenerateToken();

        $this->redirect(route('noerd.login'));
    }

    public function setSidebarVisibility(bool $showSidebar): void
    {
        $showSidebar ? session()->forget('hide_sidebar') : session(['hide_sidebar' => true]);
    }

    public function setAppbarVisibility(bool $showAppbar): void
    {
        $showAppbar ? session()->forget('hide_appbar') : session(['hide_appbar' => true]);
    }
} ?>

@inject('navigation', 'Noerd\\Services\\NavigationService')

<div
        @if(count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)
            :style="isDesktop ? (showSidebar ? (showAppbar ? 'left: var(--sidebar-total-width); width: calc(100% - var(--sidebar-total-width))' : 'left: var(--sidebar-nav-width); width: calc(100% - var(--sidebar-nav-width))') : (showAppbar ? 'left: var(--sidebar-apps-width); width: calc(100% - var(--sidebar-apps-width))' : '')) : ''"
        @else
            :style="isDesktop && showAppbar ? 'left: var(--sidebar-apps-width); width: calc(100% - var(--sidebar-apps-width))' : ''"
        @endif
        @class([
        'fixed top-[calc(var(--banner-height,0px)_+_var(--impersonation-banner-height,0px)_+_var(--environment-banner-height,0px))] left-0 w-full bg-white z-40',
    ])>
    <div>
        <div class="flex py-2 gap-x-4 px-6 w-full">
            <div class=" flex border-gray-300 w-full py-1">

                {{-- Toggles ONLY the navigation. The app bar is toggled exclusively via the button
                     at the bottom of the navigation. On mobile the toggle is transient (no session),
                     so the navigation is hidden again after every page change. --}}
                <button
                    @if(count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)
                        @click="showSidebar = ! showSidebar; if (isDesktop) { $wire.setSidebarVisibility(showSidebar) }"
                    @else
                        {{-- No navigation: the button is the only way to reach the app bar --}}
                        @click="if (! isDesktop) { showSidebar = ! showSidebar; showAppbar = showSidebar } else { showAppbar = ! showAppbar; $wire.setAppbarVisibility(showAppbar) }"
                    @endif
                    type="button"
                        class="my-auto mr-6 shrink-0 text-gray-600 hover:text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><title>
                            layout-left</title>
                        <g fill="currentColor" stroke-linecap="square" stroke-linejoin="miter" stroke-miterlimit="10">
                            <rect x="2" y="4" width="20" height="16" rx="2" ry="2" fill="none" stroke="currentColor"
                                  stroke-width="2"></rect>
                            <line x1="6" y1="16" x2="6" y2="8" fill="none" stroke="currentColor"
                                  stroke-width="2"></line>
                        </g>
                    </svg>
                </button>

                <livewire:noerd::layout.quick-menu/>

                {{-- pl-4 matches gap-x-4 so the gap between the scrolling quick menu and the first
     icon equals the spacing between the icons themselves --}}
                <div class="ml-auto my-auto flex items-center gap-x-4 pl-4 shrink-0">
                    @foreach($topBarComponents as $topBarComponent)
                        <livewire:dynamic-component :component="$topBarComponent" :key="$topBarComponent" />
                    @endforeach

                    @if(NoerdAuth::user()->isAdmin())
                        <a class="shrink-0" wire:navigate href="{{route('noerd.setup')}}">
                            <x-noerd::button variant="icon" icon="cog-6-tooth" type="button">
                                <span class="sr-only">View setup</span>
                            </x-noerd::button>
                        </a>
                    @endif

                    <!-- Profile dropdown -->
                    <div x-data="{ open: false }" class="relative shrink-0">
                        <div>
                            <button x-on:click="open = ! open" x-on:click.outside="open = false" type="button"
                                    class="relative flex rounded-full bg-white focus:outline-hidden focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
                                    id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                <span class="absolute -inset-1.5"></span>
                                <span class="sr-only">Open user menu</span>
                                <div class="rounded-full text-xs bg-red-200  w-7 h-7 leading-7 text-center">
                                    {{NoerdAuth::user()->initials()}}
                                </div>
                            </button>
                        </div>

                        <div x-show="open" x-transition
                             class="absolute right-0 z-90 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-hidden"
                             role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button"
                             tabindex="-1">
                            <a wire:navigate href="{{route('noerd.profile')}}" class="block px-4 py-2 text-sm text-gray-700"
                               role="menuitem"
                               tabindex="-1" id="user-menu-item-0">{{__('Profile')}}</a>

                            <a wire:navigate wire:click="logout" class="block px-4 py-2 cursor-pointer text-sm text-gray-700"
                               role="menuitem"
                               tabindex="-1" id="user-menu-item-2">{{__('Sign Out')}}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
