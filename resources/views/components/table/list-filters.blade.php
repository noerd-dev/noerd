{{--
    The list's filter controls: the YAML picklist/date filters, one chip per
    active Excel-style column filter and the clear-all button. Rendered ONCE,
    inside the horizontally scrolling strip of the filter row — every control is
    `shrink-0`, so the strip grows and scrolls instead of squeezing or wrapping.
--}}
@foreach ($tableFilters as $tableFilter)
    @if (in_array($tableFilter['type'] ?? 'Picklist', ['ShowFrom', 'ShowUntil']))
        <x-noerd::filters.date-dropdown
            :filter="$tableFilter"
            :value="$listFilters[$tableFilter['column']] ?? ''" />
    @else
        <x-noerd::filters.picklist
            :filter="$tableFilter"
            :value="$listFilters[$tableFilter['column']] ?? ''" />
    @endif
@endforeach

@foreach ($chips as $filterChip)
    <span
        wire:key="column-filter-chip-{{ $filterChip['field'] }}"
        class="flex shrink-0 items-center gap-1 rounded-full bg-gray-100 py-0.5 pr-1 pl-2.5 text-xs font-normal whitespace-nowrap text-gray-700"
    >
        <span class="font-medium">{{ $filterChip['label'] }}:</span>
        <span class="max-w-48 truncate">{{ $filterChip['value'] }}</span>
        <button
            type="button"
            wire:click="clearColumnFilter('{{ $filterChip['field'] }}')"
            title="{{ __('Clear filter') }}"
            class="rounded-full p-0.5 text-gray-500 hover:bg-gray-200 hover:text-gray-700"
        >
            <x-dynamic-component component="heroicons::mini.solid.x-mark" class="size-3" />
        </button>
    </span>
@endforeach

@if ($hasClearAll)
    <x-noerd::button
        variant="secondary"
        size="sm"
        icon="x-mark"
        type="button"
        class="shrink-0 px-1.5"
        wire:click="clearAllListFilters"
        :title="__('Clear all filters')"
        :aria-label="__('Clear all filters')"
    />
@endif
