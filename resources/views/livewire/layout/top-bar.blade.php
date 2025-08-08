<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {

    public $selectedClientId;

    #[On('echo-private:orders.{selectedClientId},OrderCreated')]
    public function mount()
    {
        $this->selectedClientId = auth()->user()->selected_tenant_id ?? 0;
    }

    public function logout()
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        $this->redirect('/login', navigate: false);
    }

    public function changeClient()
    {
        // TODO in a action
        $user = Auth::user();
        $accessToClients = $user->tenants;
        $accessToClientsIds = $accessToClients->pluck('id')->toArray();
        if (in_array($this->selectedClientId, $accessToClientsIds) || auth()->user()->id === 1) {
            $user = Auth::user();
            $user->selected_tenant_id = $this->selectedClientId;
            $user->save();

            return $this->redirect('/');
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

@inject('navigation', 'Noerd\\Noerd\\Services\\NavigationService')

<div
    @if(count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)
        :class="showSidebar ? 'lg:left-[360px] w-[calc(100%-360px)]' : 'lg:pl-[0px] w-[calc(100%-0px)]'"
    @else
        :class="showSidebar ? 'lg:left-[80px] w-[calc(100%-80px)]' : 'lg:pl-[0px] w-[calc(100%-0px)]'"
    @endif
    @class([
    'fixed top-0 right-0 bg-white z-40',
])>
    <div>
        <!-- Button to open mobile sidebar -->
        <button x-on:click="open = ! open" type="button" class="m-2 p-2.5 text-gray-700 lg:hidden">
            <span class="sr-only">Open sidebar</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                 aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
            </svg>
        </button>

        <div class="flex gap-x-4 border-b px-4 w-full border-l border-gray-300">
            <div class=" flex border-gray-300 w-full py-1">

                <button @click="showSidebar = !showSidebar" wire:click="openSidebar" type="button"
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

                <div class="ml-auto my-auto">

                    <!-- Separator -->
                    <div class="hidden lg:block shrink-0 lg:w-px lg:bg-gray-200" aria-hidden="true"></div>

                    {{--
                    <livewire:search></livewire:search>
                    --}}

                    <div class="hidden lg:flex items-center gap-x-4 shrink-0">
                        {{--
                        <!-- Notifications -->
                        <a class="pt-2 shrink-0" href="/notifications">
                            <button type="button" class="-m-2.5 p-2.5 text-gray-400 hover:text-gray-500">
                                <span class="sr-only">View notifications</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                     stroke="currentColor"
                                     aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                                </svg>
                            </button>
                        </a>
                        --}}

                        @if(auth()->user()->isAdmin())
                            <a class="pt-2 shrink-0" href="{{route('setup')}}">
                                <button type="button" class="-m-2.5 p-2.5 text-gray-600 hover:text-gray-500">
                                    <span class="sr-only">View setup</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                         stroke-width="1.5"
                                         stroke="currentColor" class="h-6 w-6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495"/>
                                    </svg>
                                </button>
                            </a>
                        @endif

                        @if(auth()->user()->isAdmin() || auth()->user()->tenants->count() > 1)
                            <!-- Tenants -->
                            <x-noerd::select-input class="w-48! mt-0!" wire:model="selectedClientId"
                                            wire:change="changeClient">
                                @foreach(auth()->user()->tenants as $client)
                                    <option value="{{$client->id}}">{{$client->name}}</option>
                                @endforeach
                                @if(auth()->user()->id === 1)
                                    @foreach(\Noerd\Noerd\Models\Tenant::where('lost', 0)->get() as $client)
                                        <option value="{{$client->id}}">{{$client->name}}</option>
                                    @endforeach
                                @endif

                            </x-noerd::select-input>
                        @endif

                        <!-- Profile dropdown -->
                        <div x-data="{ open: false }" class="relative shrink-0">
                            <div>
                                <button x-on:click="open = ! open" x-on:click.outside="open = false" type="button"
                                        class="relative flex rounded-full bg-white focus:outline-hidden focus:ring-2 focus:ring-black focus:ring-offset-2"
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
                                <!-- Active: "bg-gray-100", Not Active: "" -->

                                <a href="{{route('profile')}}" class="block px-4 py-2 text-sm text-gray-700"
                                   role="menuitem"
                                   tabindex="-1" id="user-menu-item-0">{{__('Profile')}}</a>

                                <a wire:click="logout" class="block px-4 py-2 cursor-pointer text-sm text-gray-700"
                                   role="menuitem"
                                   tabindex="-1" id="user-menu-item-2">{{__('Sign Out')}}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@script

<script>
    setInterval(function () {
        $wire.dispatch('refreshOrderCount')
    }, 15000)
</script>

@endscript

