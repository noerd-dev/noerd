{{-- A header filter inside the list's scrolling filter strip: never shrinks, never
     wraps. `full` forces a full-width layout for hosts that lay filters out themselves. --}}
@props(['filter', 'value' => '', 'full' => false])

@php
    $label = $value ? ($filter['options'][$value] ?? $value) : '';
    $active = $value !== '';
    $isCustomDate = $active && !isset($filter['options'][$value]);
@endphp

{{-- The panels are anchored with `position: fixed` so they escape a horizontally scrolling
     filter bar (an `overflow-x` ancestor clips absolutely positioned children vertically too). --}}
{{-- "Select date" opens the picker on click and from the keyboard (Enter/Space,
     Arrow Right), not only on hover; Escape closes it and returns to the trigger. --}}
<div x-data="{ open: false, showDatePicker: false, customDate: @js($isCustomDate ? (string) $value : '') }"
     @click.outside="open = false; showDatePicker = false"
     @keydown.escape="if (showDatePicker) { $event.preventDefault(); $event.stopPropagation(); showDatePicker = false; $refs[{{ \Illuminate\Support\Js::from('dateBtn_' . $filter['column']) }}].focus() } else if (open) { $event.preventDefault(); $event.stopPropagation(); open = false; $refs[{{ \Illuminate\Support\Js::from('trigger_' . $filter['column']) }}].focus() }"
     class="relative {{ $full ? 'w-full' : 'shrink-0' }}">
    <button x-ref="trigger_{{ $filter['column'] }}" @click="open = !open; showDatePicker = false" type="button"
            :aria-expanded="open" aria-haspopup="true"
            class="{{ $active ? 'border-brand-primary! border-solid! border-2!' : 'border border-dashed border-zinc-300' }} {{ $full ? 'w-full' : '' }} flex items-center gap-1 rounded-md px-3 h-8 py-1 text-sm leading-[1.375rem] focus:outline-none focus:ring-2 focus:ring-brand-border whitespace-nowrap">
        <span>{{ $filter['label'] }}</span>
        @if($active)
            <span class="text-gray-400 mx-0.5">|</span>
            <span>{{ $label }}</span>
        @endif
        <x-icon name="chevron-down" mini class="ml-1 size-3.5 text-gray-400" />
    </button>
    {{-- Main dropdown --}}
    <div x-show="open" x-cloak x-transition
         x-anchor.fixed.bottom-start.offset.4="$refs[{{ \Illuminate\Support\Js::from('trigger_' . $filter['column']) }}]"
         class="z-50 w-48 origin-top-left rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-hidden">
        @foreach($filter['options'] ?? [] as $key => $option)
            <button type="button"
                    wire:click="$set({{ \Illuminate\Support\Js::from('listFilters.' . $filter['column']) }}, {{ \Illuminate\Support\Js::from((string) $key) }})"
                    x-on:click="open = false; showDatePicker = false; $nextTick(() => $wire.storeActiveListFilters())"
                    @mouseenter="showDatePicker = false"
                    class="block w-full px-4 py-2 text-left text-sm {{ $value === $key ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }} focus:bg-gray-50 focus:outline-hidden">
                {{ $option }}
            </button>
        @endforeach
        <div class="border-t border-zinc-200 mt-1">
            <button type="button" @mouseenter="showDatePicker = true" x-ref="dateBtn_{{ $filter['column'] }}"
                    x-on:click="showDatePicker = ! showDatePicker; if (showDatePicker) { $nextTick(() => $refs.dateInput.focus()) }"
                    @keydown.arrow-right.prevent="showDatePicker = true; $nextTick(() => $refs.dateInput.focus())"
                    :aria-expanded="showDatePicker" aria-haspopup="true"
                    class="flex w-full items-center justify-between px-4 py-2 text-left text-sm {{ $isCustomDate ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }} focus:bg-gray-50 focus:outline-hidden">
                <span>{{ __('Select date') }}</span>
                <x-icon name="chevron-right" mini class="size-3.5 text-gray-400" />
            </button>
        </div>
    </div>
    {{-- Date picker sub-panel --}}
    <div x-show="open && showDatePicker" x-cloak x-transition x-anchor.fixed.right-start="$refs[{{ \Illuminate\Support\Js::from('dateBtn_' . $filter['column']) }}]"
         class="z-50 ml-1 w-56 rounded-md bg-white p-4 shadow-lg ring-1 ring-black/5 focus:outline-hidden">
        <input type="date" x-model="customDate" x-ref="dateInput" aria-label="{{ __('Select date') }}"
               class="w-full rounded-md border border-zinc-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-border" />
        <div class="mt-3 flex justify-end gap-2">
            <button type="button" @click="showDatePicker = false; $refs[{{ \Illuminate\Support\Js::from('dateBtn_' . $filter['column']) }}].focus()"
                    class="rounded-md px-3 py-1 text-sm text-gray-600 hover:bg-gray-100">
                {{ __('Cancel') }}
            </button>
            <button type="button"
                    @click="if (customDate) { $wire.set({{ \Illuminate\Support\Js::from('listFilters.' . $filter['column']) }}, customDate); open = false; showDatePicker = false; $nextTick(() => $wire.storeActiveListFilters()); }"
                    class="rounded-md bg-brand-primary px-3 py-1 text-sm text-brand-primary-text hover:opacity-90">
                {{ __('Apply') }}
            </button>
        </div>
    </div>
</div>
