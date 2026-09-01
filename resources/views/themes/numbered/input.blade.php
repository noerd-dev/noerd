{{-- Numbered variant of forms.input: gray numbered row, right-aligned label, control on the right. --}}
@props([
    'field' => null,
    'name' => '',
    'type' => 'text',
    'readonly' => false,
    'placeholder' => null,
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $type = $field['type'] ?? $type;
    $readonly = $field['readonly'] ?? $readonly;
    $placeholder = $field['placeholder'] ?? $placeholder;
    $live = $field['live'] ?? $live;
@endphp

<x-noerd::detail.numbered-row :field="$field">
    <input
        {{ $readonly ? 'readonly' : '' }}
        autocomplete="off"
        @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
        class="block h-9 w-full appearance-none rounded-none border border-zinc-400 bg-white py-1.5 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:border-dotted focus:border-zinc-600 focus:ring-0 focus:outline-none sm:text-sm"
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        @if ($type === 'date')
            x-data="noerdDateInput({ name: @js($name), length: 10 })"
        @elseif ($type === 'time')
            x-data="noerdDateInput({ name: @js($name), length: 5 })"
        @endif
    />
</x-noerd::detail.numbered-row>
