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
        'mt-0!' => $size === 'lg',
        //'lg:ml-[340px] modal-full-sidebar' => $iteration >= 1,
       // 'lg:ml-[324px] modal-full-sidebar' => $iteration === 2,
       // 'lg:ml-[344px] modal-full-sidebar' => $iteration === 3,
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
         class="relative my-auto w-full h-full py-14  items-center justify-center"
         x-transition:enter="transition transform ease-out duration-100"
         x-transition:enter-start="translate-y-1/2"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition transform ease-in duration-100"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
    >
        <div class="bg-white max-w-7xl mx-auto h-full rounded shadow-sm relative">

            <!-- Close Button -->
            <button @click="show = !show" wire:click.prevent="downModal('{{$modal}}', '{{$source}}')" type="button" @class([
                'absolute right-0 top-2 mt-1 pt-2 pr-4 mx-auto my-auto',
            //    'right-1 top-2!' => $size === 'lg',
            //    'ml-[710px]' => $size === 'sm',
        ])>
                <div
                    class="hover:bg-gray-100 z-50 hover:text-black border rounded-sm p-1.5 mt-0.5 text-gray-600 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
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
