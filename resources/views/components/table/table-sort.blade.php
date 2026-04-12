<th style="width: {{$width}}%;@if($minWidth ?? null) min-width: {{$minWidth}}px;@endif" scope="col"
    class="text-{{$align}} border-r last:border-r-0 first:pl-6 sticky top-0 z-10 border-b border-gray-300 bg-brand-navi/75 py-3.5 pr-3 pl-2 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter">
    <div class="flex top-5 whitespace-nowrap">
        @if($field !== 'action')
            @php
                $isSortable = !in_array($field, $notSortableColumns ?? []);
            @endphp
            @if($isSortable)
                <button type="button" class="text-black @if($align === 'right') ml-auto pr-2 @endif" wire:click="sortBy('{{$field}}')">
                    {{ __($label) }}
                </button>
                @if($sortField === $field)
                    <x-noerd::button variant="icon"
                                     :icon="$sortAsc ? 'chevron-up' : 'chevron-down'"
                                     type="button"
                                     wire:click="sortBy('{{$field}}')"
                                     class="ml-2"/>
                @endif
            @else
                <span class="text-black @if($align === 'right') ml-auto pr-2 @endif">
                    {{ __($label) }}
                </span>
            @endif
        @endif
    </div>
</th>
