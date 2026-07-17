{{-- Excel-style column filter: funnel button in the header cell opening a popover.
     Included from table-sort for every filterable column. Expects: field, type,
     options, filterValue, align. --}}
@php
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

<div wire:key="column-filter-{{ $field }}-{{ md5($filterValue) }}"
     x-data="{ open: false, value: @js($filterValue) }"
     @click.outside="open = false"
     class="relative {{ ($align ?? 'left') === 'right' ? 'ml-1' : 'ml-auto' }}">
    <button type="button"
            @click="open = ! open"
            x-ref="{{ $filterRef }}"
            title="{{ __('Filter') }}"
            class="flex items-center rounded p-0.5 {{ $filterActive ? 'text-brand-primary' : 'text-gray-400 opacity-0 group-hover/th:opacity-100 focus:opacity-100 hover:text-gray-600' }}"
            :class="open && 'opacity-100'">
        @if($filterActive)
            <x-dynamic-component component="heroicons::solid.funnel" class="size-3.5"/>
        @else
            <x-dynamic-component component="heroicons::outline.funnel" class="size-3.5"/>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition
         @click.stop
         @keydown.escape.stop="open = false"
         x-anchor.bottom-end="$refs.{{ $filterRef }}"
         class="z-90 w-56 whitespace-normal rounded-md bg-white p-2 text-left font-normal shadow-lg ring-1 ring-black/5 focus:outline-hidden">
        @if($isBoolFilter)
            @foreach([['', __('All')], ['1', __('Yes')], ['0', __('No')]] as [$optionValue, $optionLabel])
                <button type="button"
                        @click="$wire.setColumnFilter('{{ $field }}', '{{ $optionValue }}'); open = false"
                        class="block w-full rounded px-3 py-1.5 text-left text-sm {{ $filterValue === $optionValue ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                    {{ $optionLabel }}
                </button>
            @endforeach
        @elseif($hasOptionFilter)
            <div class="max-h-64 overflow-y-auto">
                <button type="button"
                        @click="$wire.setColumnFilter('{{ $field }}', ''); open = false"
                        class="block w-full rounded px-3 py-1.5 text-left text-sm {{ $filterValue === '' ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                    {{ __('All') }}
                </button>
                @foreach($filterOptions as $option)
                    <button type="button"
                            @click="$wire.setColumnFilter('{{ $field }}', @js((string) $option['value'])); open = false"
                            class="block w-full rounded px-3 py-1.5 text-left text-sm {{ $filterValue === (string) $option['value'] ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                        {{ __($option['label'] ?? $option['value']) }}
                    </button>
                @endforeach
            </div>
        @else
            <div class="p-1">
                <input type="text"
                       x-model="value"
                       x-ref="filterInput"
                       x-init="$watch('open', o => o && $nextTick(() => $refs.filterInput.focus()))"
                       @keydown.enter.prevent="$wire.setColumnFilter('{{ $field }}', value); open = false"
                       placeholder="{{ $placeholder }}"
                       class="w-full rounded-md border border-zinc-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-border"/>
                <div class="mt-2 flex items-center justify-end gap-2">
                    @if($filterActive)
                        <button type="button"
                                @click="value = ''; $wire.clearColumnFilter('{{ $field }}'); open = false"
                                class="rounded-md px-3 py-1 text-sm text-gray-600 hover:bg-gray-100">
                            {{ __('Clear filter') }}
                        </button>
                    @endif
                    <button type="button"
                            @click="$wire.setColumnFilter('{{ $field }}', value); open = false"
                            class="rounded-md bg-brand-primary px-3 py-1 text-sm text-white hover:opacity-90">
                        {{ __('Apply') }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
