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
        class="focus:ring-brand-border block w-full appearance-none rounded-sm border border-zinc-200 bg-white py-1 ps-2 pe-2 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:ring-1 focus:outline-none sm:text-sm"
        {{ $readonly ? 'readonly' : '' }}
    ></textarea>
</x-noerd::detail.numbered-row>
