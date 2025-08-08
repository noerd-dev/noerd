<th style="width: {{$width}}%;" scope="col"
    class="py-3 pl-2 text-{{$align}} text-sm  bg-gray-50 font-semibold tracking-wide text-black border first:border-l border-black/10 border-l-0">
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
