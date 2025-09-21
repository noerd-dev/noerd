<th style="width: {{$width}}%;" scope="col"
    class="text-{{$align}} border-r last:border-r-0 first:pl-6 sticky top-0 z-10 border-b border-gray-300 bg-brand-navi/75 py-3.5 pr-3 pl-2 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter">
    <div class="flex top-5 whitespace-nowrap">
        @if($field !== 'action')
            <button type="button" class="text-black @if($align === 'right') ml-auto pr-2 @endif" wire:click="sortBy('{{$field}}')">
                {{ __($label) }}
            </button>
            @if($sortField === $field)
                <button type="button"
                        wire:click="sortBy('{{$field}}')"
                        class="inline-flex ml-2 items-center px-2.5 rounded-full text-xs font-medium bg-gray-200 text-black">
                    @if($sortAsc)
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-3 h-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/>
                        </svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-3 h-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    @endif
                </button>
            @endif
        @endif
    </div>
</th>