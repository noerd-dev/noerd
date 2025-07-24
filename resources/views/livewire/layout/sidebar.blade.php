@inject('navigation', 'Nywerk\Noerd\Services\NavigationService')

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
                'hidden bg-white border-r pt-[57px] border-gray-300 my-0 transition-[width] lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:flex-col flex'
            ])>
            <div class="text-xs text-center">
                @foreach(auth()->user()->selectedTenant()?->tenantApps as $tenantApp)
                    <a wire:navigate @if($tenantApp->is_active) href="{{route($tenantApp->route)}}"
                       @else class="opacity-50" @endif ">
                    <div
                        @class(['!bg-brand-highlight/5  border-brand-highlight!' => session('currentApp') === $tenantApp->name,
                                    'hover:bg-brand-bg flex mt-4 h-[45px] w-[45px] rounded-sm  mx-auto'])>
                        <x-dynamic-component
                            class="{{ session('currentApp') === $tenantApp->name  ? 'stroke-brand-highlight border-brand-highlight' :
                                'stroke-black border-transparent hover:!border-gray-500' }}
                                border-l-2"
                            :component="'noerd::'.$tenantApp->icon"/>
                    </div>
                    <div x-show="showSidebar" class="text-gray-900 text-[11px] mt-1">{{$tenantApp->title}}</div>
                    </a>
                @endforeach
            </div>
        </div>

        @if(count($navigation->subMenu()) > 0 || count($navigation->blockMenus()) > 0)
            <!-- Second column sidebar -->
            <div x-show="showSidebar" :class="showSidebar ? 'lg:w-[279px] ml-[80px]' : 'ml-[50px]'" @class([
            'hidden lg:fixed lg:inset-y-0 pt-[49px] lg:z-40 bg-white lg:flex lg:flex-col flex ',
        ])>
                <livewire:layout.sidebar-navigation :navigation="$navigation->subMenu()"
                                                    :navigations="$navigation->blockMenus()"/>
            </div>
        @endif
    </div>
</div>
