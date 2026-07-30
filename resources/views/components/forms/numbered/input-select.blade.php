{{-- Numbered variant of forms.input-select: gray numbered row, right-aligned label, select on the right. --}}
@props([
    'field' => null,
    'name' => '',
    'options' => [],
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $options = $field['options'] ?? $options;
    $live = $field['live'] ?? $live;
@endphp

<x-noerd::detail.numbered-row :field="$field">
    <select
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="block h-9 w-full appearance-none rounded-none border border-zinc-400 bg-white py-1 ps-2 pe-2 text-base sm:text-sm text-zinc-700 placeholder-zinc-400 focus:border-dotted focus:border-zinc-600 focus:ring-0 focus:outline-none disabled:text-zinc-500 disabled:placeholder-zinc-400/70"
        id="{{ $name }}"
    >
        @foreach ($options as $option)
            @isset($option['value'])
                <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
            @else
                <option>{{ __($option) }}</option>
            @endisset
        @endforeach
    </select>
</x-noerd::detail.numbered-row>
