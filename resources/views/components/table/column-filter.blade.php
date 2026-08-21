{{-- Excel-style column filter: funnel button opening a popover. Included from
     table-sort for every filterable column of a table list, and from
     list/grid-controls for a grid list. Expects: field, type, options,
     filterValue, align. Optional: filterLabel — when set, the trigger renders as
     a standalone labeled chip instead of the hover-revealed header funnel. --}}
@php
    $filterLabel = $filterLabel ?? null;
    $filterValue = (string) ($filterValue ?? '');
    $filterType = $type ?? 'text';
    $filterOptions = $options ?? [];
    $filterActive = $filterValue !== '';
    $isBoolFilter = in_array($filterType, ['bool', 'boolean', 'inversebool'], true);
    $hasOptionFilter = ! $isBoolFilter && ! empty($filterOptions);
    $filterRef = 'funnelBtn_' . preg_replace('/[^A-Za-z0-9_]/', '_', $field);
    $placeholder = match (true) {
        in_array($filterType, ['number', 'currency'], true) => __('e.g. >0 or <=10'),
        in_array($filterType, ['date', 'datetime'], true) => __('e.g. >=2026-01-01'),
        default => __('e.g. =value or text'),
    };
@endphp

<div
    wire:key="column-filter-{{ $field }}-{{ md5($filterValue) }}"
    x-data="{ open: false, value: @js($filterValue) }"
    @click.outside="open = false"
    class="relative {{ $filterLabel === null ? (($align ?? 'left') === 'right' ? 'ml-1' : 'ml-auto') : '' }}"
>
    <button
        type="button"
        @click="open = ! open"
        x-ref="{{ $filterRef }}"
        title="{{ __('Filter') }}"
        @class([
            // Standalone chip (grid lists): always visible, since there is no
            // header cell to hover. Header funnel: revealed on hover/focus.
            'inline-flex items-center gap-1 rounded-md border px-2 py-1 text-sm' => $filterLabel !== null,
            'border-brand-primary text-brand-primary' => $filterLabel !== null && $filterActive,
            'border-gray-300 text-gray-600 hover:border-gray-400 hover:text-gray-900' => $filterLabel !== null && ! $filterActive,
            'flex items-center rounded p-0.5' => $filterLabel === null,
            'text-brand-primary' => $filterLabel === null && $filterActive,
            'text-gray-400 opacity-0 group-hover/th:opacity-100 focus:opacity-100 hover:text-gray-600' => $filterLabel === null && ! $filterActive,
        ])
        :class="open && 'opacity-100'"
    >
        @if ($filterActive)
            <x-dynamic-component component="heroicons::solid.funnel" class="size-3.5" />
        @else
            <x-dynamic-component component="heroicons::outline.funnel" class="size-3.5" />
        @endif
        @if ($filterLabel !== null)
            <span>{{ $filterLabel }}</span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        @click.stop
        @keydown.escape.stop="open = false"
        x-anchor.bottom-end="$refs.{{ $filterRef }}"
        class="z-90 w-56 rounded-md bg-white p-2 text-left font-normal whitespace-normal shadow-lg ring-1 ring-black/5 focus:outline-hidden"
    >
        @if ($isBoolFilter)
            @foreach ([['', __('All')], ['1', __('Yes')], ['0', __('No')]] as [$optionValue, $optionLabel])
                <button
                    type="button"
                    @click="$wire.setColumnFilter('{{ $field }}', '{{ $optionValue }}'); open = false"
                    class="block w-full rounded px-3 py-1.5 text-left text-sm {{ $filterValue === $optionValue ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }}"
                >
                    {{ $optionLabel }}
                </button>
            @endforeach
        @elseif ($hasOptionFilter)
            <div class="max-h-64 overflow-y-auto">
                <button
                    type="button"
                    @click="$wire.setColumnFilter('{{ $field }}', ''); open = false"
                    class="block w-full rounded px-3 py-1.5 text-left text-sm {{ $filterValue === '' ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }}"
                >
                    {{ __('All') }}
                </button>
                @foreach ($filterOptions as $option)
                    <button
                        type="button"
                        @click="$wire.setColumnFilter('{{ $field }}', @js((string) $option['value'])); open = false"
                        class="block w-full rounded px-3 py-1.5 text-left text-sm {{ $filterValue === (string) $option['value'] ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }}"
                    >
                        {{ __($option['label'] ?? $option['value']) }}
                    </button>
                @endforeach
            </div>
        @else
            <div class="p-1">
                <input
                    type="text"
                    x-model="value"
                    x-ref="filterInput"
                    x-init="$watch('open', (o) => o && $nextTick(() => $refs.filterInput.focus()))"
                    @keydown.enter.prevent="$wire.setColumnFilter('{{ $field }}', value); open = false"
                    placeholder="{{ $placeholder }}"
                    class="focus:ring-brand-border w-full rounded-md border border-zinc-300 px-2 py-1.5 text-sm focus:ring-2 focus:outline-none"
                />
                <div class="mt-2 flex items-center justify-end gap-2">
                    @if ($filterActive)
                        <button
                            type="button"
                            @click="value = ''; $wire.clearColumnFilter('{{ $field }}'); open = false"
                            class="rounded-md px-3 py-1 text-sm text-gray-600 hover:bg-gray-100"
                        >
                            {{ __('Clear filter') }}
                        </button>
                    @endif
                    <button
                        type="button"
                        @click="$wire.setColumnFilter('{{ $field }}', value); open = false"
                        class="bg-brand-primary rounded-md px-3 py-1 text-sm text-white hover:opacity-90"
                    >
                        {{ __('Apply') }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
