@props(['filter', 'value' => ''])

@php
    $label = $value ? ($filter['options'][$value] ?? $value) : '';
    $active = $value !== '';
    $isCustomDate = $active && !isset($filter['options'][$value]);
@endphp

<div x-data="{ open: false, showDatePicker: false, customDate: '{{ $isCustomDate ? $value : '' }}' }"
     @click.outside="open = false; showDatePicker = false"
     class="relative mr-4">
    <button @click="open = !open; showDatePicker = false" type="button"
            class="{{ $active ? '!border-brand-primary !border-solid !border-2' : 'border border-dashed border-zinc-300' }} flex items-center gap-1 rounded-md px-3 h-8 text-sm focus:outline-none focus:ring-2 focus:ring-brand-border whitespace-nowrap">
        <span>{{ $filter['label'] }}</span>
        @if($active)
            <span class="text-gray-400 mx-0.5">|</span>
            <span>{{ $label }}</span>
        @endif
        <svg class="ml-1 size-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
    </button>
    {{-- Main dropdown --}}
    <div x-show="open" x-transition
         class="absolute left-0 z-50 mt-1 w-48 origin-top-left rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-hidden">
        @foreach($filter['options'] ?? [] as $key => $option)
            <button type="button"
                    wire:click="$set('listFilters.{{ $filter['column'] }}', '{{ $key }}')"
                    x-on:click="open = false; showDatePicker = false; $nextTick(() => $wire.storeActiveListFilters())"
                    @mouseenter="showDatePicker = false"
                    class="block w-full px-4 py-2 text-left text-sm {{ $value === $key ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                {{ $option }}
            </button>
        @endforeach
        <div class="border-t border-zinc-200 mt-1">
            <button type="button" @mouseenter="showDatePicker = true" x-ref="dateBtn_{{ $filter['column'] }}"
                    class="flex w-full items-center justify-between px-4 py-2 text-left text-sm {{ $isCustomDate ? 'bg-gray-100 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                <span>{{ __('noerd_show_from_custom_date') }}</span>
                <svg class="size-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>
    {{-- Date picker sub-panel --}}
    <div x-show="open && showDatePicker" x-transition x-anchor.right-start="$refs.dateBtn_{{ $filter['column'] }}"
         class="z-50 ml-1 w-56 rounded-md bg-white p-4 shadow-lg ring-1 ring-black/5 focus:outline-hidden">
        <input type="date" x-model="customDate"
               class="w-full rounded-md border border-zinc-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-border" />
        <div class="mt-3 flex justify-end gap-2">
            <button type="button" @click="showDatePicker = false"
                    class="rounded-md px-3 py-1 text-sm text-gray-600 hover:bg-gray-100">
                {{ __('Cancel') }}
            </button>
            <button type="button"
                    @click="if (customDate) { $wire.set('listFilters.{{ $filter['column'] }}', customDate); open = false; showDatePicker = false; $nextTick(() => $wire.storeActiveListFilters()); }"
                    class="rounded-md bg-brand-primary px-3 py-1 text-sm text-white hover:opacity-90">
                {{ __('noerd_show_from_apply') }}
            </button>
        </div>
    </div>
</div>
