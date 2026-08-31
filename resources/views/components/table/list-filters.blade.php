{{--
    The list header's filter controls: the YAML picklist/date filters, one chip per
    active Excel-style column filter and the clear-all button. Rendered ONCE — below
    `xl` they stack full-width in the filter drawer, from `xl` on they sit inline on
    the header row. The layout switch is pure CSS, so there is no second copy and
    hence no key prefix to keep the two apart.
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
        class="flex items-center gap-1 rounded-full bg-gray-100 py-0.5 pr-1 pl-2.5 text-xs font-normal whitespace-nowrap text-gray-700 max-xl:w-full max-xl:justify-start xl:shrink-0"
    >
        <span class="font-medium">{{ $filterChip['label'] }}:</span>
        <span class="truncate">{{ $filterChip['value'] }}</span>
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
    {{-- One button in both layouts: a labelled full-width row in the drawer, a bare
         icon button on the header row. `!my-0` cancels the button's row-centring
         auto margin, which the drawer's flex COLUMN would otherwise stretch. --}}
    <x-noerd::button
        variant="secondary"
        size="sm"
        icon="x-mark"
        type="button"
        class="max-xl:!my-0 max-xl:w-full xl:shrink-0 xl:px-1.5"
        wire:click="clearAllListFilters"
        :title="__('Clear all filters')"
    >
        <span class="xl:hidden">{{ __('Clear all filters') }}</span>
    </x-noerd::button>
@endif
