{{-- Numbered variant of forms.input-textarea: gray numbered row, top-aligned label, textarea on the right. --}}
@props([
    'field' => null,
    'name' => '',
    'value' => '',
    'readonly' => false,
    'rows' => 8,
])

@php
    $name = $field['name'] ?? $name;
    $value = $field['value'] ?? $value;
    $readonly = $field['readonly'] ?? $readonly;
    $rows = $field['rows'] ?? $rows;
@endphp

<x-noerd::detail.numbered-row :field="$field" :labelTop="true">
    <textarea
        placeholder="{{ $value }}"
        wire:model="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        class="w-full border border-zinc-200 rounded-sm block appearance-none text-base sm:text-sm py-1 leading-[1.375rem] ps-2 pe-2 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border"
        {{ $readonly ? 'readonly' : '' }}
    ></textarea>
</x-noerd::detail.numbered-row>
