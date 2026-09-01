{{-- Numbered variant of forms.input-textarea: gray numbered row, top-aligned label, textarea on the right. --}}
@props([
    'field' => null,
    'name' => '',
    'placeholder' => null,
    'live' => false,
    'readonly' => false,
    'rows' => 8,
])

@php
    $name = $field['name'] ?? $name;
    $placeholder = $field['placeholder'] ?? $placeholder;
    $live = $field['live'] ?? $live;
    $readonly = $field['readonly'] ?? $readonly;
    $rows = $field['rows'] ?? $rows;
@endphp

<x-noerd::detail.numbered-row :field="$field" :labelTop="true">
    <textarea
        @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        class="block w-full appearance-none rounded-none border border-zinc-400 bg-white py-1.5 ps-2 pe-2 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:border-dotted focus:border-zinc-600 focus:ring-0 focus:outline-none sm:text-sm"
        {{ $readonly ? 'readonly' : '' }}
    ></textarea>
</x-noerd::detail.numbered-row>
