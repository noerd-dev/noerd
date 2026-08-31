{{-- The list header's filter controls, rendered twice from list-header: once inline in the
     header row and once inside the filter drawer that takes over when the header row runs out
     of space. Both copies bind to the same `listFilters` properties, so either one drives the
     list. `$keyPrefix` keeps the wire:key of the chips unique between the two copies, `$stacked`
     switches the controls to the full-width drawer layout. --}}
@php
    $stacked ??= false;
    $keyPrefix ??= '';
@endphp

@foreach ($tableFilters as $tableFilter)
    @if (in_array($tableFilter['type'] ?? 'Picklist', ['ShowFrom', 'ShowUntil']))
        <x-noerd::filters.date-dropdown
            :filter="$tableFilter"
            :value="$listFilters[$tableFilter['column']] ?? ''"
            :full="$stacked"
        />
    @else
        <x-noerd::filters.picklist
            :filter="$tableFilter"
            :value="$listFilters[$tableFilter['column']] ?? ''"
            :full="$stacked"
        />
    @endif
@endforeach

@foreach ($chips as $filterChip)
    <span
        wire:key="{{ $keyPrefix }}column-filter-chip-{{ $filterChip['field'] }}"
        @class([
            'flex shrink-0 items-center gap-1 rounded-full bg-gray-100 py-0.5 pr-1 pl-2.5 text-xs font-normal whitespace-nowrap text-gray-700',
            'w-full justify-start' => $stacked,
        ])
    >
        <span class="font-medium">{{ $filterChip['label'] }}:</span>
        <span class="{{ $stacked ? 'truncate' : '' }}">{{ $filterChip['value'] }}</span>
        <button
            type="button"
            wire:click="clearColumnFilter('{{ $filterChip['field'] }}')"
            title="{{ __('Clear filter') }}"
            class="ml-auto rounded-full p-0.5 text-gray-500 hover:bg-gray-200 hover:text-gray-700"
        >
            <x-dynamic-component component="heroicons::mini.solid.x-mark" class="size-3" />
        </button>
    </span>
@endforeach

@if ($hasClearAll)
    @if ($stacked)
        <x-noerd::button
            variant="secondary"
            size="sm"
            icon="x-mark"
            type="button"
            class="w-full"
            wire:click="clearAllListFilters"
        >
            {{ __('Clear all filters') }}
        </x-noerd::button>
    @else
        <x-noerd::button
            class="shrink-0"
            variant="icon"
            size="sm"
            icon="x-mark"
            type="button"
            wire:click="clearAllListFilters"
            :title="__('Clear all filters')"
        />
    @endif
@endif
