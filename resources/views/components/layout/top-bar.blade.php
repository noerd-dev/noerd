<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;

new class () extends Component {
    public $selectedTenantId;

    public function mount(): void
    {
        $this->selectedTenantId = TenantHelper::getSelectedTenantId() ?? 0;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        $this->redirect('/login');
    }

    public function changeClient()
    {
        $user = Auth::user();
        $accessToClients = $user->tenants;
        $accessToClientsIds = $accessToClients->pluck('id')->toArray();
        if (in_array($this->selectedTenantId, $accessToClientsIds)) {
            TenantHelper::setSelectedTenantId($this->selectedTenantId);

            $redirectUrl = '/';
            $referer = request()->header('Referer');

            if ($referer) {
                $path = parse_url($referer, PHP_URL_PATH);
                $segments = explode('/', mb_trim($path, '/'));
                $appPrefix = $segments[0] ?? null;

                if ($appPrefix) {
                    // System paths that are always accessible
                    $systemPaths = ['setup', 'profile', 'dashboard', 'no-tenant'];

                    if (in_array($appPrefix, $systemPaths)) {
                        $redirectUrl = $referer;
                    } else {
                        $newTenant = Tenant::find($this->selectedTenantId);
                        $hasApp = $newTenant?->tenantApps()
                            ->whereRaw('LOWER(name) = ?', [mb_strtolower($appPrefix)])
                            ->exists();

                        if ($hasApp) {
                            $redirectUrl = $referer;
                        }
                    }
                }
            }

            return $this->redirect($redirectUrl);
        }
    }

    public function openSidebar(): void
    {
        if (session('hide_sidebar')) {
            session()->forget('hide_sidebar');
        } else {
            session(['hide_sidebar' => true]);
        }
    }
} ?>

@inject('navigation', 'Noerd\\Services\\NavigationService')

<div
        @if(count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)
            :style="showSidebar && window.innerWidth >= 1280 ? (showAppbar ? 'left: var(--sidebar-total-width); width: calc(100% - var(--sidebar-total-width))' : 'left: var(--sidebar-nav-width); width: calc(100% - var(--sidebar-nav-width))') : ''"
        @else
            :style="showSidebar && window.innerWidth >= 1280 && showAppbar ? 'left: var(--sidebar-apps-width); width: calc(100% - var(--sidebar-apps-width))' : ''"
        @endif
        @class([
        'fixed top-[calc(var(--banner-height,0px)_+_var(--impersonation-banner-height,0px)_+_var(--environment-banner-height,0px))] left-0 w-full bg-white z-40',
    ])>
    <div>
        <div class="flex py-2 gap-x-4 px-6 w-full">
            <div class=" flex border-gray-300 w-full py-1">

                <button @click="showSidebar = !showSidebar; if(window.innerWidth >= 1280) $wire.openSidebar()" type="button"
                        class="my-auto mr-6 text-gray-600 hover:text-gray-500">
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

                <livewire:layout.quick-menu/>

                <div class="ml-auto my-auto flex items-center gap-x-4 shrink-0">
                    @if(auth()->user()->isAdmin())
                        <a class="shrink-0" wire:navigate href="{{route('setup')}}">
                            <x-noerd::button variant="icon" icon="cog-6-tooth" type="button">
                                <span class="sr-only">View setup</span>
                            </x-noerd::button>
                        </a>
                    @endif

                    @if(config('noerd.features.multi_tenant') && auth()->user()->tenants->count() > 1)
                        <!-- Tenants (nur Desktop) -->
                        <x-noerd::select-input class="hidden xl:block h-8 w-48! mt-0!" wire:model="selectedTenantId" wire:change="changeClient">
                            @foreach(auth()->user()->tenants as $client)
                                <option value="{{$client->id}}">{{$client->name}}</option>
                            @endforeach
                        </x-noerd::select-input>
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
                                    {{auth()->user()->initials()}}
                                </div>
                            </button>
                        </div>

                        <div x-show="open" x-transition
                             class="absolute right-0 z-90 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-hidden"
                             role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button"
                             tabindex="-1">
                            <a wire:navigate href="{{route('profile')}}" class="block px-4 py-2 text-sm text-gray-700"
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
