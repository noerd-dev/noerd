@php $isFullscreen = session('modal_fullscreen', false); @endphp
<div
    x-noerd::dialog
    x-show="open"
    x-init="setTimeout(() => open = true, 0)"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @class([
        'fixed transition-opacity w-full ml-auto inset-0 flex z-50',
    ])
>
    <!-- Overlay -->
    <div x-noerd::dialog:overlay
        @class([
            'fixed inset-0 bg-gray-800/50',
           // 'lg:ml-[356px] hidden' => $iteration === 2,
            //'lg:ml-[340px]' => $iteration === 3,
        ])>
    </div>

    <!-- Panel min-h-screen h-full -->
    <div x-show="open" id="modal" modal="{{$modal}}"
         @class([
            'relative w-full justify-center',
            'h-[100dvh] my-0 items-start',
            'sm:h-[100dvh] sm:items-start' => $isFullscreen,
            'sm:h-auto sm:max-h-[100dvh] sm:py-14 sm:my-auto sm:items-center' => !$isFullscreen,
        ])
         x-transition:enter="transition transform ease-out duration-100"
         x-transition:enter-start="translate-y-1/2"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition transform ease-in duration-100"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
    >
        <div @class([
            'bg-white mx-auto shadow-sm relative',
            'max-w-full h-[100dvh] rounded-none',
            'sm:max-w-full sm:h-[calc(100dvh-3.5rem)] sm:mt-14 sm:rounded-none' => $isFullscreen,
            'sm:max-w-7xl sm:h-full sm:max-h-[calc(100dvh-3.5rem)] sm:rounded' => !$isFullscreen,
        ])>

            <!-- Fullscreen Toggle Button (nur Desktop) -->
            <button wire:click.prevent="toggleFullscreen" type="button" class="hidden sm:block absolute right-0 top-4 pt-2 pr-16 mx-auto my-auto">
                <div class="hover:bg-gray-100 z-50 hover:text-black border rounded-sm p-1.5 text-gray-600 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                    <span class="sr-only">Toggle fullscreen</span>
                    @if($isFullscreen)
                        <!-- Minimize Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                        </svg>
                    @else
                        <!-- Maximize Icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                    @endif
                </div>
            </button>

            <!-- Close Button -->
            <button @click="show = !show" wire:click.prevent="downModal('{{$modal}}', '{{$source}}', '{{$modalKey}}')" type="button" @class([
                'absolute right-0 top-4 pt-2 pr-6 mx-auto my-auto',
        ])>
                <div
                    class="hover:bg-gray-100 z-50 hover:text-black border rounded-sm p-1.5 text-gray-600 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                    <span class="sr-only">Close modal</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
            </button>

            <div x-data="{ isModal: true}">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
